<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['A.md' => '# A', 'B.md' => '# B', 'C.md' => '# C']);
    resolve(IndexVault::class)->handle($this->vault);
    $this->note = fn (string $path): Note => Note::query()->where('path', $path)->sole();
});

it('stars and unstars notes for the sidebar', function (): void {
    $a = ($this->note)('A.md');

    $this->fromRoute('notes.show', [$this->vault, $a])
        ->put(route('note-star.update', [$this->vault, $a]), ['starred' => true])
        ->assertRedirectToRoute('notes.show', [$this->vault, $a]);

    $this->get(route('notes.show', [$this->vault, $a]))
        ->assertInertia(fn ($page) => $page
            ->where('note.starred', true)
            ->where('favourites', [['id' => $a->id, 'title' => 'A', 'path' => 'A.md']]));

    $this->put(route('note-star.update', [$this->vault, $a]), ['starred' => false]);

    expect($a->refresh()->starred_at)->toBeNull();

    $this->put(route('note-star.update', [$this->vault, $a]), ['starred' => 'maybe'])->assertSessionHasErrors('starred');
});

it('remembers recently opened notes, newest first', function (): void {
    $this->travelTo(now()->subMinutes(2));
    $this->get(route('notes.show', [$this->vault, ($this->note)('B.md')]));
    $this->travelBack();
    $this->get(route('notes.show', [$this->vault, ($this->note)('C.md')]))
        ->assertInertia(fn ($page) => $page
            ->where('recent_notes.0.title', 'C')
            ->where('recent_notes.1.title', 'B')
            ->count('recent_notes', 2));
});
