<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('takes a visit to a note that has gone back to its vault, saying why', function (): void {
    $vault = vaultWithFiles(['Kept.md' => 'kept']);
    resolve(IndexVault::class)->handle($vault);

    $this->withHeader('X-Inertia', 'true')->get(route('notes.show', [$vault, 999]))
        ->assertRedirectToRoute('vaults.show', $vault)
        ->assertInertiaFlash('toast.type', 'info')
        ->assertInertiaFlash('toast.message', 'That page isn’t there any more. Its note may have been deleted, or moved or renamed outside Kite.');

    $this->withHeader('X-Inertia', 'true')->get(route('notes.show', [999, Note::query()->sole()]))->assertRedirectToRoute('vaults.index');
});

it('keeps a real not found for requests that aren\'t page visits', function (): void {
    $vault = vaultWithFiles();

    $this->get(route('notes.show', [$vault, 999]))->assertNotFound();
    $this->getJson(route('note-panes.show', [$vault, 999]))->assertNotFound();
    $this->putJson(route('notes.update', [$vault, 999]), ['contents' => 'x'])->assertNotFound();
    $this->get('/no-such-page')->assertNotFound();
});
