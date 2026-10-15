<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vault;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Locked notes: individual notes encrypted on disk with a key stretched from the vault's
 * passphrase.
 *
 * The passphrase is turned into a key with Argon2id; the salt, the cost and a small check value
 * (to tell a wrong passphrase from a right one) live in `.kite/lock.json`, so a vault can be
 * unlocked on any machine. A locked note keeps its file name; its contents become a short notice
 * followed by the note encrypted with XChaCha20-Poly1305, which also detects tampering. The key
 * itself is never written anywhere: Kite holds it in memory while the vault is unlocked.
 */
final readonly class NoteLocks
{
    public const string HEADER = 'X-Kite-Vault-Key';

    private const string FILE = '.kite/lock.json';

    private const string MARKER = '<!-- kite-locked v1 -->';

    private const string ADDITIONAL_DATA = 'kite-locked v1';

    private const string CHECK = 'kite';

    public function __construct(private VaultFilesystem $files = new VaultFilesystem)
    {
        //
    }

    /**
     * Choose the vault's passphrase, returning the key it unlocks.
     */
    public function setUp(Vault $vault, #[SensitiveParameter] string $passphrase): string
    {
        throw_if($this->isSetUp($vault), InvalidArgumentException::class, 'This vault already has a passphrase.');

        $settings = [
            'version' => 1,
            'kdf' => 'argon2id13',
            'salt' => base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES)),
            'opslimit' => config()->integer('kite.locks.opslimit'),
            'memlimit' => config()->integer('kite.locks.memlimit'),
        ];

        $key = $this->derive($passphrase, $settings);
        $settings['check'] = $this->seal(self::CHECK, $key);

        File::ensureDirectoryExists(dirname($this->file($vault)));
        File::put($this->file($vault), json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return $key;
    }

    public function isLocked(string $contents): bool
    {
        return str_starts_with(mb_ltrim($contents, "\u{FEFF}"), self::MARKER);
    }

    public function isSetUp(Vault $vault): bool
    {
        return $this->settings($vault) !== null;
    }

    /**
     * The vault's key for a passphrase, or null when the passphrase is wrong.
     */
    public function unlock(Vault $vault, #[SensitiveParameter] string $passphrase): ?string
    {
        $settings = $this->settings($vault);

        if ($settings === null) {
            return null;
        }

        $key = $this->derive($passphrase, $settings);

        return $this->open($settings['check'], $key) === self::CHECK ? $key : null;
    }

    /**
     * The key sent with a request (base64 encoded), if it is this vault's key.
     */
    public function keyFrom(Vault $vault, #[SensitiveParameter] ?string $encoded): ?string
    {
        $key = base64_decode((string) $encoded, true);
        $settings = $this->settings($vault);

        if ($key === false || mb_strlen($key, '8bit') !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES || $settings === null) {
            return null;
        }

        return $this->open($settings['check'], $key) === self::CHECK ? $key : null;
    }

    /**
     * A note's text as the contents of a locked note's file.
     */
    public function lock(string $contents, #[SensitiveParameter] string $key): string
    {
        return self::MARKER."\n"
            ."This note is locked. Open it in Kite and enter the vault's passphrase to read it.\n\n"
            ."```kite-locked\n"
            .chunk_split($this->seal($contents, $key), 76, "\n")
            ."```\n";
    }

    /**
     * A locked note's text, or null when the key is wrong or the file was changed.
     */
    public function unlockNote(string $contents, #[SensitiveParameter] string $key): ?string
    {
        if (! $this->isLocked($contents) || preg_match('/^```kite-locked\n(.*?)^```/ms', $contents, $block) !== 1) {
            return null;
        }

        return $this->open((string) preg_replace('/\s+/', '', $block[1]), $key);
    }

    /**
     * @param  array{salt: string, opslimit: int, memlimit: int, ...<string, mixed>}  $settings
     */
    private function derive(#[SensitiveParameter] string $passphrase, array $settings): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            $passphrase,
            (string) base64_decode($settings['salt'], true),
            $settings['opslimit'],
            $settings['memlimit'],
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }

    private function seal(string $plaintext, #[SensitiveParameter] string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        return base64_encode($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, self::ADDITIONAL_DATA, $nonce, $key));
    }

    private function open(string $sealed, #[SensitiveParameter] string $key): ?string
    {
        $bytes = (string) base64_decode($sealed, true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            mb_substr($bytes, $nonceLength, null, '8bit'),
            self::ADDITIONAL_DATA,
            mb_substr($bytes, 0, $nonceLength, '8bit'),
            $key,
        );

        return $plaintext === false ? null : $plaintext;
    }

    /**
     * @return array{salt: string, opslimit: int, memlimit: int, check: string}|null
     */
    private function settings(Vault $vault): ?array
    {
        $file = $this->file($vault);
        $settings = File::exists($file) ? json_decode(File::get($file), true) : null;

        if (! is_array($settings) || ! is_string($settings['salt'] ?? null) || ! is_string($settings['check'] ?? null)
            || ! is_int($settings['opslimit'] ?? null) || ! is_int($settings['memlimit'] ?? null)) {
            return null;
        }

        return [
            'salt' => $settings['salt'],
            'opslimit' => $settings['opslimit'],
            'memlimit' => $settings['memlimit'],
            'check' => $settings['check'],
        ];
    }

    private function file(Vault $vault): string
    {
        return $this->files->contained($vault, $vault->path.DIRECTORY_SEPARATOR.self::FILE);
    }
}
