<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\Vault;
use App\Services\CommentStore;

function commentedNote(string $contents, array $comment = []): array
{
    $vault = vaultWithFiles(['Plan.md' => $contents]);
    resolve(IndexVault::class)->handle($vault);
    $note = Note::query()->sole();

    if ($comment !== []) {
        resolve(CommentStore::class)->add($vault, $note->path, ['body' => 'Check this', 'prefix' => '', 'suffix' => '', ...$comment]);
    }

    return [$vault, $note];
}

function highlighted(): string
{
    return "[...document.querySelectorAll('.cm-comment')].map((mark) => mark.textContent).join('|')";
}

function storedComments(Vault $vault): array
{
    return resolve(CommentStore::class)->all($vault, 'Plan.md');
}

function rewriteNote(Vault $vault, string $contents): void
{
    file_put_contents($vault->path.'/Plan.md', $contents);
    resolve(IndexVault::class)->handle($vault);
}

it('comments on a selection and lists it in the rail', function (): void {
    [$vault, $note] = commentedNote("Ship the release on Friday.\n\nThen rest.");

    visit(route('notes.show', [$vault, $note]))
        ->click('@note-editor')
        ->keys('@note-editor', ['Control+Home', 'Shift+End'])
        ->click('@add-comment')
        ->assertVisible('@comment-draft')
        ->fill('[data-test=comment-draft] textarea', 'Is Friday realistic?')
        ->click('[data-test=comment-draft] button[type=submit]')
        ->assertSeeIn('@comment-body', 'Is Friday realistic?')
        ->assertScript(highlighted(), 'Ship the release on Friday.')
        ->assertSeeIn('@toggle-comments', '1')
        ->assertNoJavaScriptErrors();

    expect(collect(storedComments($vault)[0])->only(['quote', 'prefix', 'suffix'])->all())->toBe([
        'quote' => 'Ship the release on Friday.',
        'prefix' => '',
        'suffix' => "\n\nThen rest.",
    ]);
});

it('finds commented text again after the note changed outside kite', function (): void {
    [$vault, $note] = commentedNote('Ship the release on Friday.', ['quote' => 'on Friday', 'prefix' => 'Ship the release ', 'suffix' => '.']);
    rewriteNote($vault, "Intro paragraph.\n\nMeet on Friday too.\nShip the release on Friday.");

    visit(route('notes.show', [$vault, $note]))
        ->click('@toggle-comments')
        ->assertScript(highlighted(), 'on Friday')
        ->assertScript("document.querySelector('.cm-comment').closest('.cm-line').textContent", 'Ship the release on Friday.')
        ->assertMissing('@orphaned-comments')
        ->assertNoJavaScriptErrors();
});

it('follows a reworded passage by its context and saves the new anchor', function (): void {
    [$vault, $note] = commentedNote('Ship the release on Friday. Then celebrate.', ['quote' => 'on Friday', 'prefix' => 'Ship the release ', 'suffix' => '. Then celebrate.']);
    rewriteNote($vault, 'Ship the release next Monday. Then celebrate.');

    visit(route('notes.show', [$vault, $note]))
        ->assertScript(highlighted(), 'next Monday')
        ->wait(1.5)
        ->assertNoJavaScriptErrors();

    expect(storedComments($vault)[0]['quote'])->toBe('next Monday');
});

it('marks comments whose text is gone as orphaned', function (): void {
    [$vault, $note] = commentedNote('Something else entirely.', ['quote' => 'on Friday', 'prefix' => 'Ship the release ', 'suffix' => '.']);

    visit(route('notes.show', [$vault, $note]))
        ->click('@toggle-comments')
        ->assertSeeIn('@orphaned-comments', 'Check this')
        ->assertScript(highlighted(), '')
        ->assertNoJavaScriptErrors();
});

it('keeps comments on their text while editing in kite', function (): void {
    [$vault, $note] = commentedNote('Ship the release on Friday.', ['quote' => 'on Friday', 'prefix' => 'Ship the release ', 'suffix' => '.']);

    visit(route('notes.show', [$vault, $note]))
        ->click('@note-editor')
        ->keys('@note-editor', ['Control+Home'])
        ->typeSlowly('@note-editor', 'Plan: ')
        ->assertScript(highlighted(), 'on Friday')
        ->wait(1.5)
        ->assertNoJavaScriptErrors();

    expect(storedComments($vault)[0]['prefix'])->toBe('Plan: Ship the release ');
});

it('resolves, reopens and deletes comments', function (): void {
    [$vault, $note] = commentedNote('Ship the release on Friday.', ['quote' => 'on Friday']);

    visit(route('notes.show', [$vault, $note]))
        ->click('@toggle-comments')
        ->click('[data-test=comment] [aria-label=Resolve]')
        ->assertScript(highlighted(), '')
        ->assertSee('Show resolved (1)')
        ->click('@toggle-resolved')
        ->click('[data-test=comment] [aria-label=Reopen]')
        ->assertScript(highlighted(), 'on Friday')
        ->click('[data-test=comment] [aria-label=Delete]')
        ->assertMissing('@comment')
        ->assertNoJavaScriptErrors();

    expect(storedComments($vault))->toBe([])
        ->and(file_exists($vault->path.'/.kite'))->toBeFalse();
});

it('explains when the comments file is damaged', function (): void {
    [$vault, $note] = commentedNote('Ship the release on Friday.');
    mkdir($vault->path.'/.kite/comments', recursive: true);
    file_put_contents($vault->path.'/.kite/comments/Plan.md.json', '{ broken');

    visit(route('notes.show', [$vault, $note]))
        ->click('@toggle-comments')
        ->assertSeeIn('@comments-error', '.kite/comments/Plan.md.json')
        ->assertNoJavaScriptErrors();
});
