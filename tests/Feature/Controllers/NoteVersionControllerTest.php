<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\SaveNote;
use App\Models\Note;
use App\Models\NoteVersion;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['Note.md' => 'original', 'Other.md' => 'other']);
    resolve(IndexVault::class)->handle($this->vault);
    $this->note = Note::query()->where('path', 'Note.md')->sole();
    resolve(SaveNote::class)->handle($this->note, 'edited');
});

it('lists versions newest first', function (): void {
    $this->getJson(route('note-versions.index', [$this->vault, $this->note]))
        ->assertOk()
        ->assertJsonPath('versions.0.source', 'edit')
        ->assertJsonPath('versions.0.size', 6)
        ->assertJsonPath('versions.1.source', 'external')
        ->assertJsonCount(2, 'versions');
});

it('shows a version with the one before it', function (): void {
    [$original, $edited] = $this->note->versions()->oldest('id')->get()->all();

    $this->getJson(route('note-versions.show', [$this->vault, $this->note, $edited]))
        ->assertOk()
        ->assertJson(['id' => $edited->id, 'contents' => 'edited', 'previous' => 'original']);

    $this->getJson(route('note-versions.show', [$this->vault, $this->note, $original]))
        ->assertJson(['contents' => 'original', 'previous' => null]);
});

it('restores a version and keeps the replaced text in history', function (): void {
    $original = $this->note->versions()->oldest('id')->first();

    $this->post(route('note-version-restores.store', [$this->vault, $this->note, $original]))
        ->assertRedirectToRoute('notes.show', [$this->vault, $this->note])
        ->assertInertiaFlash('toast.type', 'success');

    expect(file_get_contents($this->vault->path.'/Note.md'))->toBe('original')
        ->and($this->note->versions()->latest('id')->value('source')->value)->toBe('restore')
        ->and($this->note->versions()->pluck('contents')->all())->toContain('edited');
});

it('only serves versions of the note in the url', function (): void {
    $other = Note::query()->where('path', 'Other.md')->sole();
    $version = NoteVersion::factory()->for($other)->create();

    $this->getJson(route('note-versions.show', [$this->vault, $this->note, $version]))->assertNotFound();
});
