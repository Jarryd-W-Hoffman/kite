<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Services\NoteLocks;

it('locks a note, keeps editing it, locks the vault and unlocks it again', function (): void {
    $vault = vaultWithFiles(['Secret.md' => "# Secret\n\nThe code is swordfish"]);
    resolve(IndexVault::class)->handle($vault);
    $file = $vault->path.'/Secret.md';

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@note-actions')
        ->click('@lock-note')
        ->assertVisible('@passphrase-dialog')
        ->fill('passphrase', 'correct horse battery')
        ->fill('passphrase_confirmation', 'correct horse battery')
        ->click('Set passphrase and lock')
        ->assertSee('Locked “Secret”.')
        ->assertVisible('@locked-badge')
        ->assertVisible('@tree-lock')
        ->assertSeeIn('@note-editor', 'The code is swordfish');

    $locks = resolve(NoteLocks::class);
    $key = $locks->unlock($vault, 'correct horse battery');

    expect(file_get_contents($file))->not->toContain('swordfish')
        ->and($locks->isLocked((string) file_get_contents($file)))->toBeTrue();

    $page->keys('@note-editor', 'Control+End')
        ->typeSlowly('@note-editor', ' and marlin')
        ->assertSee('Saved');

    expect($locks->unlockNote((string) file_get_contents($file), (string) $key))->toContain('The code is swordfish and marlin')
        ->and(file_get_contents($file))->not->toContain('marlin');

    $page->click('@note-actions')
        ->click('@lock-vault')
        ->assertVisible('@locked-note')
        ->assertDontSee('swordfish')
        ->fill('passphrase', 'wrong passphrase')
        ->click('Unlock')
        ->assertSee('That passphrase is not right.')
        ->fill('passphrase', 'correct horse battery')
        ->click('Unlock')
        ->assertSeeIn('@note-editor', 'The code is swordfish and marlin')
        ->click('@note-actions')
        ->click('@remove-lock')
        ->assertSee('Removed the lock from “Secret”.')
        ->assertMissing('@locked-badge')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($file))->toBe("# Secret\n\nThe code is swordfish and marlin");
});

it('asks for the passphrase again after the app reloads', function (): void {
    $vault = vaultWithFiles(['Secret.md' => '# Secret', 'Plain.md' => '# Plain']);
    resolve(IndexVault::class)->handle($vault);
    $locks = resolve(NoteLocks::class);
    $key = $locks->setUp($vault, 'correct horse battery');
    file_put_contents($vault->path.'/Secret.md', $locks->lock("# Secret\n\nHidden text", $key));
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->where('path', 'Secret.md')->sole()]))
        ->assertVisible('@locked-note')
        ->assertDontSee('Hidden text')
        ->fill('passphrase', 'correct horse battery')
        ->click('Unlock')
        ->assertSeeIn('@note-editor', 'Hidden text')
        ->click('Plain')
        ->assertSeeIn('@note-editor', 'Plain')
        ->click('[data-test=tree-lock]')
        ->assertSeeIn('@note-editor', 'Hidden text')
        ->assertNoJavaScriptErrors();
});
