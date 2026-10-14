<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Services\CommentStore;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['Work/Plan.md' => "# Plan\n\nShip the release on Friday.", 'Other.md' => '# Other']);
    resolve(IndexVault::class)->handle($this->vault);
    $this->note = Note::query()->where('path', 'Work/Plan.md')->sole();
    $this->store = resolve(CommentStore::class);
    $this->file = $this->vault->path.'/.kite/comments/Work/Plan.md.json';
});

function addComment(object $test, array $comment = []): array
{
    return $test->store->add($test->vault, $test->note->path, ['body' => 'Check this', 'quote' => 'on Friday', 'prefix' => '', 'suffix' => '', ...$comment]);
}

it('adds a comment to a sidecar file beside the vault notes', function (): void {
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

    $file = json_decode((string) file_get_contents($this->file), true);

    expect($file['format'])->toBe(1)
        ->and($file['comments'][0])->toMatchArray(['quote' => 'on Friday', 'body' => 'Is Friday realistic?'])
        ->and($file['comments'][0]['id'])->toMatch('/^[0-9a-z]{26}$/');
});

it('shares the note comments in the order they were added', function (): void {
    $first = addComment($this);
    $second = addComment($this, ['body' => 'Second']);

    $this->get(route('notes.show', [$this->vault, $this->note]))
        ->assertInertia(fn ($page) => $page
            ->where('comments.0.id', $first['id'])
            ->where('comments.1.id', $second['id'])
            ->missing('comments_error'));
});

it('edits, resolves, reopens and re-anchors a comment', function (): void {
    $comment = addComment($this);
    $url = route('note-comments.update', [$this->vault, $this->note, $comment['id']]);

    $this->putJson($url, ['body' => 'Moved to Monday?', 'resolved' => true])
        ->assertOk()
        ->assertJson(['body' => 'Moved to Monday?', 'resolved' => true, 'quote' => 'on Friday']);

    $this->putJson($url, ['resolved' => false, 'quote' => 'on Monday', 'prefix' => 'release ', 'suffix' => null])
        ->assertOk()
        ->assertJson(['body' => 'Moved to Monday?', 'resolved' => false, 'quote' => 'on Monday', 'prefix' => 'release ', 'suffix' => '']);
});

it('deletes a comment and the file once it is empty', function (): void {
    $comment = addComment($this);

    $this->deleteJson(route('note-comments.destroy', [$this->vault, $this->note, $comment['id']]))->assertNoContent();

    expect(file_exists($this->file))->toBeFalse()
        ->and(is_dir($this->vault->path.'/.kite'))->toBeFalse();
});

it('only touches comments on the note in the url', function (): void {
    $other = Note::query()->where('path', 'Other.md')->sole();
    $comment = $this->store->add($this->vault, $other->path, ['body' => 'x', 'quote' => 'Other', 'prefix' => '', 'suffix' => '']);

    $this->deleteJson(route('note-comments.destroy', [$this->vault, $this->note, $comment['id']]))->assertNotFound();
    $this->putJson(route('note-comments.update', [$this->vault, $this->note, $comment['id']]), ['body' => 'y'])->assertNotFound();
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

it('leaves a damaged comments file alone and says so', function (): void {
    @mkdir(dirname($this->file), recursive: true);
    file_put_contents($this->file, '{ not json');

    $this->postJson(route('note-comments.store', [$this->vault, $this->note]), ['body' => 'Hi', 'quote' => 'Ship'])
        ->assertConflict();

    $this->get(route('notes.show', [$this->vault, $this->note]))
        ->assertInertia(fn ($page) => $page
            ->where('comments', [])
            ->where('comments_error', 'This note’s comments file (.kite/comments/Work/Plan.md.json) could not be read, so comments are unavailable until it is fixed.'));

    expect(file_get_contents($this->file))->toBe('{ not json');
});
