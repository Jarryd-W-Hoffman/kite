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
 * Notes are encrypted with a random vault key. That key is stored in `.kite/lock.json` twice,
 * each copy encrypted ("wrapped") with a key of its own: one stretched from the passphrase with
 * Argon2id, and one from a random recovery key the user keeps somewhere safe. Either opens the
 * vault, and changing the passphrase or the recovery key only rewraps the vault key, so no note
 * has to be encrypted again. A small check value tells the vault key from any other.
 *
 * Vaults set up before recovery keys (version 1) used the stretched passphrase itself as the
 * vault key; the first unlock with the passphrase wraps it, upgrading the file in place.
 *
 * A locked note keeps its file name; its contents become a short notice followed by the note
 * encrypted with XChaCha20-Poly1305, which also detects tampering. The vault key is never written
 * anywhere unwrapped: Kite holds it in memory while the vault is unlocked.
 */
final readonly class NoteLocks
{
    public const string HEADER = 'X-Kite-Vault-Key';

    private const string FILE = '.kite/lock.json';

    private const string MARKER = '<!-- kite-locked v1 -->';

    private const string ADDITIONAL_DATA = 'kite-locked v1';

    private const string CHECK = 'kite';

    private const string PASSPHRASE_WRAP = 'kite-vault-key passphrase';

    private const string RECOVERY_WRAP = 'kite-vault-key recovery';

    /** Crockford's base32: no I, L, O or U, so a written-down key can't be misread. */
    private const string RECOVERY_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int RECOVERY_LENGTH = 32;

    /**
     * Choose the vault's passphrase, returning the new vault key it unlocks.
     */
    public function setUp(Vault $vault, #[SensitiveParameter] string $passphrase): string
    {
        throw_if($this->isSetUp($vault), InvalidArgumentException::class, 'This vault already has a passphrase.');

        $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();

        $this->save($vault, [
            ...$this->wrapWithPassphrase($key, $passphrase),
            'recovery' => null,
            'check' => $this->seal(self::CHECK, $key),
        ]);

        return $key;
    }

    /**
     * Give the vault a new passphrase, keeping its key (and so every locked note) as it is.
     */
    public function changePassphrase(Vault $vault, #[SensitiveParameter] string $key, #[SensitiveParameter] string $passphrase): void
    {
        $settings = $this->settingsFor($vault, $key);

        $this->save($vault, [...$settings, ...$this->wrapWithPassphrase($key, $passphrase)]);
    }

    /**
     * Make a new recovery key for the vault, replacing any earlier one, and return it formatted
     * for writing down (`XXXX-XXXX-…`). It is shown once and never stored.
     */
    public function createRecoveryKey(Vault $vault, #[SensitiveParameter] string $key): string
    {
        $settings = $this->settingsFor($vault, $key);
        $recoveryKey = '';

        for ($i = 0; $i < self::RECOVERY_LENGTH; $i++) {
            $recoveryKey .= self::RECOVERY_ALPHABET[random_int(0, 31)];
        }

        $this->save($vault, [...$settings, 'recovery' => $this->seal($key, $this->recoveryWrappingKey($recoveryKey), self::RECOVERY_WRAP)]);

        return implode('-', mb_str_split($recoveryKey, 4));
    }

    public function hasRecoveryKey(Vault $vault): bool
    {
        return ($this->settings($vault)['recovery'] ?? null) !== null;
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

        if ($settings['key'] !== null) {
            return $this->open($settings['key'], $this->derive($passphrase, $settings), self::PASSPHRASE_WRAP);
        }

        $key = $this->derive($passphrase, $settings);

        if ($this->open($settings['check'], $key) !== self::CHECK) {
            return null;
        }

        $this->save($vault, [...$settings, ...$this->wrapWithPassphrase($key, $passphrase)]);

        return $key;
    }

    /**
     * The vault's key for a recovery key, or null when it's wrong or the vault has none. Spaces,
     * dashes, case and look-alike characters (O for 0, I or L for 1) don't matter.
     */
    public function unlockWithRecoveryKey(Vault $vault, #[SensitiveParameter] string $recoveryKey): ?string
    {
        $recovery = $this->settings($vault)['recovery'] ?? null;

        return $recovery === null ? null : $this->open($recovery, $this->recoveryWrappingKey($recoveryKey), self::RECOVERY_WRAP);
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

    /**
     * The vault key wrapped with a passphrase, with a fresh salt and the current cost.
     *
     * @return array{version: int, kdf: string, salt: string, opslimit: int, memlimit: int, key: string}
     */
    private function wrapWithPassphrase(#[SensitiveParameter] string $key, #[SensitiveParameter] string $passphrase): array
    {
        $settings = [
            'version' => 2,
            'kdf' => 'argon2id13',
            'salt' => base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES)),
            'opslimit' => config()->integer('kite.locks.opslimit'),
            'memlimit' => config()->integer('kite.locks.memlimit'),
        ];

        return [...$settings, 'key' => $this->seal($key, $this->derive($passphrase, $settings), self::PASSPHRASE_WRAP)];
    }

    /**
     * A recovery key holds 160 random bits, so a fast hash is enough to turn it into a key.
     */
    private function recoveryWrappingKey(#[SensitiveParameter] string $recoveryKey): string
    {
        $normalised = strtr(mb_strtoupper((string) preg_replace('/[\s-]+/', '', $recoveryKey)), ['O' => '0', 'I' => '1', 'L' => '1']);

        return sodium_crypto_generichash(self::RECOVERY_WRAP."\n".$normalised, '', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    }

    /**
     * The vault's settings, once the key has been checked against them.
     *
     * @return array{version: int, salt: string, opslimit: int, memlimit: int, check: string, key: string|null, recovery: string|null}
     */
    private function settingsFor(Vault $vault, #[SensitiveParameter] string $key): array
    {
        $settings = $this->settings($vault);

        throw_if($settings === null || $this->open($settings['check'], $key) !== self::CHECK, InvalidArgumentException::class, "That is not this vault's key.");

        return $settings;
    }

    /**
     * Write the settings atomically: the file holds the only copies of the wrapped vault key, so a
     * half-written one would lose every locked note.
     *
     * @param  array<string, mixed>  $settings
     */
    private function save(Vault $vault, array $settings): void
    {
        $settings = array_filter(['kdf' => 'argon2id13', ...$settings], fn (mixed $value): bool => $value !== null);
        $file = $this->file($vault);
        $temporaryPath = $file.'.'.bin2hex(random_bytes(4)).'.tmp';

        File::ensureDirectoryExists(dirname($file));
        File::put($temporaryPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        File::move($temporaryPath, $file);
    }

    private function seal(string $plaintext, #[SensitiveParameter] string $key, string $additionalData = self::ADDITIONAL_DATA): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        return base64_encode($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $additionalData, $nonce, $key));
    }

    private function open(string $sealed, #[SensitiveParameter] string $key, string $additionalData = self::ADDITIONAL_DATA): ?string
    {
        $bytes = (string) base64_decode($sealed, true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            mb_substr($bytes, $nonceLength, null, '8bit'),
            $additionalData,
            mb_substr($bytes, 0, $nonceLength, '8bit'),
            $key,
        );

        return $plaintext === false ? null : $plaintext;
    }

    /**
     * @return array{version: int, salt: string, opslimit: int, memlimit: int, check: string, key: string|null, recovery: string|null}|null
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
            'version' => is_string($settings['key'] ?? null) ? 2 : 1,
            'salt' => $settings['salt'],
            'opslimit' => $settings['opslimit'],
            'memlimit' => $settings['memlimit'],
            'check' => $settings['check'],
            'key' => is_string($settings['key'] ?? null) ? $settings['key'] : null,
            'recovery' => is_string($settings['recovery'] ?? null) ? $settings['recovery'] : null,
        ];
    }

    private function file(Vault $vault): string
    {
        return $vault->path.DIRECTORY_SEPARATOR.self::FILE;
    }
}
