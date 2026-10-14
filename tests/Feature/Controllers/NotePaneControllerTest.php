<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('returns a note for a split pane', function (): void {
    $vault = vaultWithFiles(['Side.md' => "# Side\n#tag"]);
    resolve(IndexVault::class)->handle($vault);
    $note = Note::query()->sole();

    $this->getJson(route('note-panes.show', [$vault, $note]))
        ->assertOk()
        ->assertJsonPath('note.id', $note->id)
        ->assertJsonPath('note.contents', "# Side\n#tag")
        ->assertJsonPath('note.tags', ['tag'])
        ->assertJsonPath('note.outline.0.text', 'Side');
});

it('does not return notes from another vault', function (): void {
    $vault = vaultWithFiles();
    $other = vaultWithFiles(['Other.md' => 'x']);
    resolve(IndexVault::class)->handle($other);

    $this->getJson(route('note-panes.show', [$vault, $other->notes()->sole()]))->assertNotFound();
});
