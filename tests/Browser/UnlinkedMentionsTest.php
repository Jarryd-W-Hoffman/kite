<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('lists unlinked mentions and links them in one click', function (): void {
    $vault = vaultWithFiles([
        'Kite.md' => '# Kite',
        'Journal.md' => "# Journal\nFlew my kite today.",
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Kite.md')->sole()]));

    $page->assertSeeIn('@unlinked-mentions', 'Flew my kite today.')
        ->click('Link')
        ->assertSee('Linked the mention in Journal.')
        ->assertSee('No unlinked mentions.')
        ->assertSee('Flew my [[kite]] today.')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Journal.md'))->toBe("# Journal\nFlew my [[kite]] today.");
});
