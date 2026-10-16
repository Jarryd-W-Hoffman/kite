<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\LoadFlashcards;
use App\Actions\LockNote;
use App\Models\Note;
use App\Services\Flashcards\Card;
use App\Services\Flashcards\ScheduleStore;
use App\Services\NoteLocks;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Spanish.md' => "---\ntags: [flashcards/spanish]\n---\n# Spanish\n\nperro ::: dog\ngato :: cat",
        'Science.md' => "# Science #flashcards\n\nThe ==mitochondria== is the powerhouse\n\nName the planets\n?\nMercury, Venus…",
        'Secret.md' => "# Secret #flashcards\n\nhidden :: card",
        'Templates/Deck.md' => "# Deck #flashcards\n\ntemplate :: card",
        'Plain.md' => "# Plain\n\nnot :: a card here",
    ]);
    resolve(IndexVault::class)->handle($this->vault);
    resolve(LockNote::class)->handle(Note::query()->where('path', 'Secret.md')->sole(), resolve(NoteLocks::class)->setUp($this->vault, 'correct horse'));

    $this->key = fn (string $kind, string $front): string => new Card($kind, $front, '', 1)->key();
    $this->schedule = fn (): array => json_decode((string) file_get_contents($this->vault->path.'/.kite/flashcards.json'), true);
});

it('lists decks and today’s cards from notes tagged #flashcards', function (): void {
    $this->get(route('flashcards.index', [$this->vault, 'today' => '2026-10-16']))
        ->assertInertia(fn ($page) => $page
            ->component('flashcards/index')
            ->where('today', '2026-10-16')
            ->where('deck', null)
            ->where('decks', [
                ['name' => 'General', 'due' => 0, 'new' => 2, 'total' => 2],
                ['name' => 'spanish', 'due' => 0, 'new' => 3, 'total' => 3],
            ])
            ->where('queue', function (Collection $queue): bool {
                expect($queue->pluck('deck')->all())->toBe(['General', 'General', 'spanish', 'spanish', 'spanish'])
                    ->and($queue[0]['front'])->toContain('[…]')
                    ->and($queue[0]['back'])->toContain('<strong>mitochondria</strong>')
                    ->and($queue[0])->toMatchArray(['kind' => 'cloze', 'is_new' => true, 'intervals' => ['again' => 0, 'hard' => 1, 'good' => 1, 'easy' => 4]])
                    ->and($queue[0]['note'])->toMatchArray(['title' => 'Science', 'line' => 3])
                    ->and($queue[2]['front'])->toBe("<p>perro</p>\n")
                    ->and($queue[2]['note']['line'])->toBe(6);

                return true;
            }));
});

it('studies one deck', function (): void {
    $this->get(route('flashcards.index', [$this->vault, 'today' => '2026-10-16', 'deck' => 'spanish', 'study' => 1]))
        ->assertInertia(fn ($page) => $page->where('deck', 'spanish')->where('studying', true)->has('queue', 3)->has('decks', 2));

    $this->get(route('flashcards.index', [$this->vault, 'today' => '2026-10-16']))
        ->assertInertia(fn ($page) => $page->where('studying', false));
});

it('records reviews in the vault and brings cards back when they’re due', function (): void {
    $perro = ($this->key)('reversed', 'perro');

    $this->postJson(route('flashcard-reviews.store', $this->vault), ['key' => $perro, 'grade' => 'good', 'today' => '2026-10-16'])
        ->assertOk()
        ->assertExactJson(['due' => '2026-10-17', 'interval' => 1, 'intervals' => ['again' => 0, 'hard' => 1, 'good' => 3, 'easy' => 3]]);

    expect(($this->schedule)())->toMatchArray(['version' => 1])
        ->and(($this->schedule)()['cards'][$perro])->toMatchArray(['due' => '2026-10-17', 'interval' => 1, 'reps' => 1, 'introduced' => '2026-10-16']);

    $this->get(route('flashcards.index', [$this->vault, 'today' => '2026-10-16', 'deck' => 'spanish']))
        ->assertInertia(fn ($page) => $page->where('decks.1', ['name' => 'spanish', 'due' => 0, 'new' => 2, 'total' => 3])->has('queue', 2));

    $this->get(route('flashcards.index', [$this->vault, 'today' => '2026-10-17', 'deck' => 'spanish']))
        ->assertInertia(fn ($page) => $page
            ->where('decks.1', ['name' => 'spanish', 'due' => 1, 'new' => 2, 'total' => 3])
            ->where('queue.0.front', "<p>perro</p>\n")
            ->where('queue.0.is_new', false));
});

it('brings in at most a day’s worth of new cards per deck', function (): void {
    $lines = implode("\n", array_map(fn (int $n): string => "word{$n} :: palabra{$n}", range(1, 25)));
    writeVaultFile($this->vault, 'Spanish.md', "# Spanish #flashcards/spanish\n\n{$lines}");
    resolve(IndexVault::class)->handle($this->vault);

    expect(resolve(LoadFlashcards::class)->handle($this->vault, '2026-10-16')['decks'][1])->toBe(['name' => 'spanish', 'due' => 0, 'new' => 20, 'total' => 25]);

    foreach (range(1, 5) as $n) {
        $this->postJson(route('flashcard-reviews.store', $this->vault), ['key' => ($this->key)('basic', "word{$n}"), 'grade' => 'good', 'today' => '2026-10-16']);
    }

    expect(resolve(LoadFlashcards::class)->handle($this->vault, '2026-10-16')['decks'][1])->toBe(['name' => 'spanish', 'due' => 0, 'new' => 15, 'total' => 25])
        ->and(resolve(LoadFlashcards::class)->handle($this->vault, '2026-10-17')['decks'][1])->toBe(['name' => 'spanish', 'due' => 5, 'new' => 20, 'total' => 25]);
});

it('refuses reviews that don’t make sense', function (array $review, string $error): void {
    $this->postJson(route('flashcard-reviews.store', $this->vault), [...['key' => str_repeat('a', 20), 'grade' => 'good', 'today' => '2026-10-16'], ...$review])
        ->assertJsonValidationErrors($error);
})->with([
    'bad key' => [['key' => '../../etc'], 'key'],
    'bad grade' => [['grade' => 'perfect'], 'grade'],
    'bad date' => [['today' => '16/10/2026'], 'today'],
]);

it('shows the server’s date when the window doesn’t send one', function (): void {
    $this->get(route('flashcards.index', $this->vault))->assertInertia(fn ($page) => $page->where('today', now()->toDateString()));
});

it('ignores a damaged schedule file and entries it can’t read', function (): void {
    file_put_contents($this->vault->path.'/.kite/flashcards.json', '{"cards": {"x": {"due": "soon"}, "y": "nope"}}');

    expect(resolve(ScheduleStore::class)->all($this->vault))->toBe([]);

    file_put_contents($this->vault->path.'/.kite/flashcards.json', 'not json');

    expect(resolve(ScheduleStore::class)->all($this->vault))->toBe([]);
});

it('skips flashcard notes whose file has gone', function (): void {
    unlink($this->vault->path.'/Spanish.md');

    expect(array_column(resolve(LoadFlashcards::class)->handle($this->vault, '2026-10-16')['decks'], 'name'))->toBe(['General']);
});
