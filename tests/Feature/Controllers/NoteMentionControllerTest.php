<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Kite.md' => '# Kite',
        'Journal.md' => "# Journal\nFlying a kite.",
    ]);

    resolve(IndexVault::class)->handle($this->vault);

    $this->kite = Note::query()->where('path', 'Kite.md')->sole();
    $this->journal = Note::query()->where('path', 'Journal.md')->sole();
});

it('loads unlinked mentions as a deferred prop', function (): void {
    $this->get(route('notes.show', [$this->vault, $this->kite]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('mentions')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('mentions', 1)
                ->where('mentions.0.title', 'Journal')
                ->where('mentions.0.line', 2)));
});

it('links a mention', function (): void {
    $this->post(route('note-mentions.store', [$this->vault, $this->kite]), ['source_id' => $this->journal->id, 'line' => 2])
        ->assertRedirectToRoute('notes.show', [$this->vault, $this->kite])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Linked the mention in Journal.']);

    expect(file_get_contents($this->vault->path.'/Journal.md'))->toBe("# Journal\nFlying a [[kite]].");
});

it('reports when a mention can no longer be linked', function (): void {
    $this->post(route('note-mentions.store', [$this->vault, $this->kite]), ['source_id' => $this->journal->id, 'line' => 1])
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'That mention has changed. Nothing was linked.']);
});

it('validates the source note belongs to the vault', function (): void {
    $other = vaultWithFiles(['Elsewhere.md' => 'kite']);
    resolve(IndexVault::class)->handle($other);

    $this->fromRoute('notes.show', [$this->vault, $this->kite])
        ->post(route('note-mentions.store', [$this->vault, $this->kite]), ['source_id' => $other->notes()->value('id'), 'line' => 0])
        ->assertSessionHasErrors(['source_id', 'line']);
});
