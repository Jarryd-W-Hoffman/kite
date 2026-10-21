<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\Vault;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Plans.md' => 'mine',
        "Plans (Jo's conflicted copy 2026-10-21).md" => 'theirs',
        'Other.md' => 'other',
    ]);
    resolve(IndexVault::class)->handle($this->vault);

    $this->plans = Note::query()->where('path', 'Plans.md')->sole();
    $this->copy = Note::query()->where('path', 'like', 'Plans (%')->sole();
});

it('tells a note about its conflicting copies, and a copy about its note', function (): void {
    $this->get(route('notes.show', [$this->vault, $this->plans]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('note.conflict_copies.0.id', $this->copy->id)
            ->where('note.conflict_of', null));

    $this->get(route('notes.show', [$this->vault, $this->copy]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('note.conflict_copies', [])
            ->where('note.conflict_of.id', $this->plans->id));
});

it('deletes a merged conflicting copy', function (): void {
    $this->delete(route('note-conflict-copies.destroy', [$this->vault, $this->plans, $this->copy]))
        ->assertRedirectToRoute('notes.show', [$this->vault, $this->plans])
        ->assertInertiaFlash('toast.message', 'Merged the conflicting copy into “Plans” and deleted it.');

    expect(Note::query()->pluck('path')->sort()->values()->all())->toBe(['Other.md', 'Plans.md'])
        ->and(file_exists($this->vault->path."/Plans (Jo's conflicted copy 2026-10-21).md"))->toBeFalse();
});

it('only deletes a conflicting copy of that note, in that vault', function (): void {
    $other = Note::query()->where('path', 'Other.md')->sole();

    $this->delete(route('note-conflict-copies.destroy', [$this->vault, $this->plans, $other]))->assertNotFound();
    $this->delete(route('note-conflict-copies.destroy', [$this->vault, $other, $this->copy]))->assertNotFound();
    $this->delete(route('note-conflict-copies.destroy', [Vault::factory()->create(), $this->plans, $this->copy]))->assertNotFound();

    expect(Note::query()->count())->toBe(3);
});
