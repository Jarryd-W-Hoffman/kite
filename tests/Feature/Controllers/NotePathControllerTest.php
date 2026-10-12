<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['A.md' => 'a', 'B.md' => 'b']);

    resolve(IndexVault::class)->handle($this->vault);
});

it('renames a note', function (): void {
    $vault = $this->vault;
    $note = Note::query()->where('path', 'A.md')->sole();

    $this->put(route('note-path.update', [$vault, $note]), ['path' => 'Archive/New'])
        ->assertRedirectToRoute('notes.show', [$vault, $note]);

    expect($note->refresh()->path)->toBe('Archive/New.md');
});

it('validates renames', function (array $data, ?string $message): void {
    $vault = $this->vault;
    $note = Note::query()->where('path', 'A.md')->sole();

    $response = $this->fromRoute('notes.show', [$vault, $note])
        ->put(route('note-path.update', [$vault, $note]), $data)
        ->assertSessionHasErrors('path');

    if ($message !== null) {
        $response->assertSessionHasErrors(['path' => $message]);
    }
})->with([
    'missing' => [[], null],
    'taken' => [['path' => 'B.md'], 'A note already exists at that path.'],
    'invalid' => [['path' => '../B.md'], 'That path is not valid.'],
]);
