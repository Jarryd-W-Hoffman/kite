<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\NoteComment;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['Plan.md' => "# Plan\n\nShip the release on Friday.", 'Other.md' => '# Other']);
    resolve(IndexVault::class)->handle($this->vault);
    $this->note = Note::query()->where('path', 'Plan.md')->sole();
});

it('adds a comment to a passage', function (): void {
    $this->postJson(route('note-comments.store', [$this->vault, $this->note]), [
        'body' => '  Is Friday realistic?  ',
        'quote' => 'on Friday',
        'prefix' => 'Ship the release ',
        'suffix' => '.',
    ])
        ->assertCreated()
        ->assertJson([
            'body' => 'Is Friday realistic?',
            'quote' => 'on Friday',
            'prefix' => 'Ship the release ',
            'suffix' => '.',
            'resolved' => false,
        ]);

    expect($this->note->comments()->count())->toBe(1);
});

it('shares the note comments in creation order', function (): void {
    $first = NoteComment::factory()->for($this->note)->create(['created_at' => now()->subMinute()]);
    $second = NoteComment::factory()->for($this->note)->resolved()->create();

    $this->get(route('notes.show', [$this->vault, $this->note]))
        ->assertInertia(fn ($page) => $page
            ->where('comments.0.id', $first->id)
            ->where('comments.1.id', $second->id)
            ->where('comments.1.resolved', true));
});

it('edits, resolves, reopens and re-anchors a comment', function (): void {
    $comment = NoteComment::factory()->for($this->note)->create(['quote' => 'on Friday']);
    $url = route('note-comments.update', [$this->vault, $this->note, $comment]);

    $this->putJson($url, ['body' => 'Moved to Monday?', 'resolved' => true])
        ->assertOk()
        ->assertJson(['body' => 'Moved to Monday?', 'resolved' => true, 'quote' => 'on Friday']);

    $this->putJson($url, ['resolved' => false, 'quote' => 'on Monday', 'prefix' => 'release ', 'suffix' => null])
        ->assertOk()
        ->assertJson(['body' => 'Moved to Monday?', 'resolved' => false, 'quote' => 'on Monday', 'prefix' => 'release ', 'suffix' => '']);
});

it('deletes a comment', function (): void {
    $comment = NoteComment::factory()->for($this->note)->create();

    $this->deleteJson(route('note-comments.destroy', [$this->vault, $this->note, $comment]))->assertNoContent();

    expect(NoteComment::query()->count())->toBe(0);
});

it('only touches comments on the note in the url', function (): void {
    $other = Note::query()->where('path', 'Other.md')->sole();
    $comment = NoteComment::factory()->for($other)->create();

    $this->deleteJson(route('note-comments.destroy', [$this->vault, $this->note, $comment]))->assertNotFound();
});

it('validates comments', function (array $data, string $field): void {
    $this->postJson(route('note-comments.store', [$this->vault, $this->note]), [...['body' => 'Hi', 'quote' => 'Ship'], ...$data])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing body' => [['body' => ''], 'body'],
    'missing quote' => [['quote' => ''], 'quote'],
    'long prefix' => [['prefix' => str_repeat('a', 65)], 'prefix'],
]);

it('removes comments with their note', function (): void {
    NoteComment::factory()->for($this->note)->create();

    $this->note->delete();

    expect(NoteComment::query()->count())->toBe(0);
});
