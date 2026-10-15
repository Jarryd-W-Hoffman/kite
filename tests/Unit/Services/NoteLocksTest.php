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
        ->and($settings)->toHaveKeys(['version', 'kdf', 'salt', 'opslimit', 'memlimit', 'check'])
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
