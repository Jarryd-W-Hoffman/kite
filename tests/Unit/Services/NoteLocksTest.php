<?php

declare(strict_types=1);

use App\Services\NoteLocks;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([]);
    $this->locks = resolve(NoteLocks::class);
});

it('sets up a passphrase once and unlocks with it', function (): void {
    expect($this->locks->isSetUp($this->vault))->toBeFalse()
        ->and($this->locks->unlock($this->vault, 'anything'))->toBeNull();

    $key = $this->locks->setUp($this->vault, 'correct horse battery');
    $settings = json_decode((string) file_get_contents($this->vault->path.'/.kite/lock.json'), true);

    expect($this->locks->isSetUp($this->vault))->toBeTrue()
        ->and(mb_strlen($key, '8bit'))->toBe(32)
        ->and($settings)->toHaveKeys(['version', 'kdf', 'salt', 'opslimit', 'memlimit', 'key', 'check'])
        ->and($settings['version'])->toBe(2)
        ->and($settings)->not->toHaveKey('recovery')
        ->and($this->locks->hasRecoveryKey($this->vault))->toBeFalse()
        ->and(glob($this->vault->path.'/.kite/*.tmp'))->toBe([])
        ->and($this->locks->unlock($this->vault, 'correct horse battery'))->toBe($key)
        ->and($this->locks->unlock($this->vault, 'wrong horse battery'))->toBeNull()
        ->and(fn () => $this->locks->setUp($this->vault, 'another one'))->toThrow(InvalidArgumentException::class);
});

it('only accepts the vault key from requests', function (): void {
    expect($this->locks->keyFrom($this->vault, base64_encode(random_bytes(32))))->toBeNull();

    $key = $this->locks->setUp($this->vault, 'correct horse battery');

    expect($this->locks->keyFrom($this->vault, base64_encode($key)))->toBe($key)
        ->and($this->locks->keyFrom($this->vault, base64_encode(random_bytes(32))))->toBeNull()
        ->and($this->locks->keyFrom($this->vault, base64_encode('short')))->toBeNull()
        ->and($this->locks->keyFrom($this->vault, 'not base64 !'))->toBeNull()
        ->and($this->locks->keyFrom($this->vault, null))->toBeNull();
});

it('encrypts notes so only the key opens them and changes are detected', function (): void {
    $key = $this->locks->setUp($this->vault, 'correct horse battery');
    $locked = $this->locks->lock("# Secret\n\nThe code is swordfish", $key);

    expect($locked)->toStartWith('<!-- kite-locked v1 -->')
        ->not->toContain('swordfish')
        ->and($this->locks->isLocked($locked))->toBeTrue()
        ->and($this->locks->isLocked("\u{FEFF}".$locked))->toBeTrue()
        ->and($this->locks->isLocked('# Plain'))->toBeFalse()
        ->and($this->locks->unlockNote($locked, $key))->toBe("# Secret\n\nThe code is swordfish")
        ->and($this->locks->unlockNote($locked, random_bytes(32)))->toBeNull()
        ->and($this->locks->unlockNote('# Plain', $key))->toBeNull()
        ->and($this->locks->unlockNote("<!-- kite-locked v1 -->\nno block", $key))->toBeNull();

    // Flip a character of the encrypted text.
    $tampered = (string) preg_replace_callback('/^```kite-locked\n(.)/m', fn (array $match): string => "```kite-locked\n".($match[1] === 'A' ? 'B' : 'A'), $locked);

    expect($this->locks->unlockNote($tampered, $key))->toBeNull()
        ->and($this->locks->lock('same', $key))->not->toBe($this->locks->lock('same', $key));
});

it('treats a damaged lock file as no passphrase', function (): void {
    mkdir($this->vault->path.'/.kite');
    file_put_contents($this->vault->path.'/.kite/lock.json', '{"salt": 1}');

    expect($this->locks->isSetUp($this->vault))->toBeFalse();
});

it('makes recovery keys that unlock the vault until they are replaced', function (): void {
    expect($this->locks->unlockWithRecoveryKey($this->vault, 'ANYTHING'))->toBeNull();

    $key = $this->locks->setUp($this->vault, 'correct horse battery');

    expect($this->locks->unlockWithRecoveryKey($this->vault, 'ANYTHING'))->toBeNull();

    $recoveryKey = $this->locks->createRecoveryKey($this->vault, $key);

    expect($recoveryKey)->toMatch('/^[0-9A-HJKMNP-TV-Z]{4}(-[0-9A-HJKMNP-TV-Z]{4}){7}$/')
        ->and($this->locks->hasRecoveryKey($this->vault))->toBeTrue()
        ->and($this->locks->unlockWithRecoveryKey($this->vault, $recoveryKey))->toBe($key)
        ->and($this->locks->unlockWithRecoveryKey($this->vault, ' '.mb_strtolower(str_replace('-', ' ', $recoveryKey)).' '))->toBe($key)
        ->and($this->locks->unlockWithRecoveryKey($this->vault, strtr($recoveryKey, ['0' => 'O', '1' => 'l'])))->toBe($key)
        ->and($this->locks->unlockWithRecoveryKey($this->vault, mb_substr($recoveryKey, 0, -1).(str_ends_with($recoveryKey, 'A') ? 'B' : 'A')))->toBeNull()
        ->and($this->locks->unlock($this->vault, 'correct horse battery'))->toBe($key);

    $replacement = $this->locks->createRecoveryKey($this->vault, $key);

    expect($this->locks->unlockWithRecoveryKey($this->vault, $recoveryKey))->toBeNull()
        ->and($this->locks->unlockWithRecoveryKey($this->vault, $replacement))->toBe($key)
        ->and(fn () => $this->locks->createRecoveryKey($this->vault, random_bytes(32)))->toThrow(InvalidArgumentException::class);
});

it('changes the passphrase without changing the vault key', function (): void {
    $key = $this->locks->setUp($this->vault, 'correct horse battery');
    $recoveryKey = $this->locks->createRecoveryKey($this->vault, $key);
    $note = $this->locks->lock('The code is swordfish', $key);

    $this->locks->changePassphrase($this->vault, $key, 'staple battery horse');

    expect($this->locks->unlock($this->vault, 'correct horse battery'))->toBeNull()
        ->and($this->locks->unlock($this->vault, 'staple battery horse'))->toBe($key)
        ->and($this->locks->unlockWithRecoveryKey($this->vault, $recoveryKey))->toBe($key)
        ->and($this->locks->unlockNote($note, $key))->toBe('The code is swordfish')
        ->and(fn () => $this->locks->changePassphrase($this->vault, random_bytes(32), 'anything else'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->locks->changePassphrase(vaultWithFiles([]), $key, 'anything else'))->toThrow(InvalidArgumentException::class);
});

it('upgrades a vault set up before recovery keys on its first unlock', function (): void {
    // A version 1 lock file: the stretched passphrase is the vault key itself.
    $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $key = sodium_crypto_pwhash(32, 'correct horse battery', $salt, config()->integer('kite.locks.opslimit'), config()->integer('kite.locks.memlimit'), SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

    mkdir($this->vault->path.'/.kite');
    file_put_contents($this->vault->path.'/.kite/lock.json', json_encode([
        'version' => 1,
        'kdf' => 'argon2id13',
        'salt' => base64_encode($salt),
        'opslimit' => config()->integer('kite.locks.opslimit'),
        'memlimit' => config()->integer('kite.locks.memlimit'),
        'check' => base64_encode($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt('kite', 'kite-locked v1', $nonce, $key)),
    ]));

    $note = $this->locks->lock('The code is swordfish', $key);

    expect($this->locks->keyFrom($this->vault, base64_encode($key)))->toBe($key)
        ->and($this->locks->hasRecoveryKey($this->vault))->toBeFalse()
        ->and($this->locks->unlock($this->vault, 'wrong horse battery'))->toBeNull()
        ->and(json_decode((string) file_get_contents($this->vault->path.'/.kite/lock.json'), true))->not->toHaveKey('key')
        ->and($this->locks->unlock($this->vault, 'correct horse battery'))->toBe($key);

    $settings = json_decode((string) file_get_contents($this->vault->path.'/.kite/lock.json'), true);

    expect($settings['version'])->toBe(2)
        ->and($settings)->toHaveKey('key')
        ->and($settings['salt'])->not->toBe(base64_encode($salt))
        ->and($this->locks->unlock($this->vault, 'correct horse battery'))->toBe($key)
        ->and($this->locks->unlockNote($note, $key))->toBe('The code is swordfish');

    $recoveryKey = $this->locks->createRecoveryKey($this->vault, $key);

    expect($this->locks->unlockWithRecoveryKey($this->vault, $recoveryKey))->toBe($key);
});
