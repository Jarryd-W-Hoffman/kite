<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\Flashcards\Card;
use App\Services\Flashcards\CardParser;
use App\Services\Flashcards\Scheduler;
use App\Services\Flashcards\ScheduleStore;
use App\Services\Markdown\MarkdownParser;
use App\Services\Row;
use App\Services\Templates;
use App\Services\VaultFilesystem;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type CardState from Scheduler
 *
 * @phpstan-type FoundCard array{card: Card, key: string, deck: string, note_id: int, note_title: string, note_path: string, state: CardState|null}
 */
final readonly class LoadFlashcards
{
    public const string TAG = 'flashcards';

    /** New cards a deck brings in per day, so a big new deck doesn't arrive all at once. */
    public const int NEW_PER_DAY = 20;

    public function __construct(
        private VaultFilesystem $files,
        private MarkdownParser $markdown,
        private CardParser $cards,
        private ScheduleStore $schedules,
        private Scheduler $scheduler,
        private Templates $templates,
        private RenderMarkdown $renderMarkdown,
    ) {
        //
    }

    /**
     * The vault's decks with how many cards each has due and new today, and the cards to study
     * today (one deck's, or every deck's), due reviews first, oldest first, then new cards.
     *
     * @param  string  $today  The reviewer's local date, Y-m-d.
     * @return array{decks: list<array{name: string, due: int, new: int, total: int}>, queue: list<array<string, mixed>>}
     */
    public function handle(Vault $vault, string $today, ?string $deck = null): array
    {
        $found = $this->find($vault);
        $decks = [];
        $queue = [];

        foreach ($this->byDeck($found) as $name => $cards) {
            $due = [];
            $new = [];
            $introduced = 0;

            foreach ($cards as $card) {
                if ($card['state'] === null) {
                    $new[] = $card;
                } else {
                    $introduced += $card['state']['introduced'] === $today ? 1 : 0;

                    if ($card['state']['due'] <= $today) {
                        $due[] = [$card['state']['due'], $card];
                    }
                }
            }

            usort($due, fn (array $a, array $b): int => [$a[0], $a[1]['note_path'], $a[1]['card']->line] <=> [$b[0], $b[1]['note_path'], $b[1]['card']->line]);
            $due = array_column($due, 1);
            $new = array_slice($new, 0, max(0, self::NEW_PER_DAY - $introduced));

            $decks[] = ['name' => $name, 'due' => count($due), 'new' => count($new), 'total' => count($cards)];

            if ($deck === null || $deck === $name) {
                array_push($queue, ...$due, ...$new);
            }
        }

        return [
            'decks' => $decks,
            'queue' => array_map(fn (array $card): array => $this->present($vault, $card, $today), $queue),
        ];
    }

    /**
     * Every card in the vault's flashcard notes, each question once.
     *
     * @return list<FoundCard>
     */
    private function find(Vault $vault): array
    {
        $tags = [];

        foreach (DB::table('note_tags')->where('vault_id', $vault->id)->where(fn (Builder $query): Builder => $query->where('name', self::TAG)->orWhere('name', 'like', self::TAG.'/%'))->get(['note_id', 'name']) as $row) {
            $tags[Row::int($row, 'note_id')][] = Row::string($row, 'name');
        }

        $notes = $this->templates->exclude(DB::table('notes')->where('vault_id', $vault->id)->whereIn('id', array_keys($tags))->where('locked', false))
            ->orderBy('path')
            ->get(['id', 'path', 'title']);
        $states = $this->schedules->all($vault);
        $found = [];

        foreach ($notes as $row) {
            $path = Row::string($row, 'path');

            if (! $this->files->exists($vault, $path)) {
                continue;
            }

            $parsed = $this->markdown->parse($this->files->read($vault, $path), $path);

            foreach ($this->cards->parse($parsed->body, $parsed->bodyStartLine + 1) as $card) {
                $key = $card->key();

                $found[$key] ??= [
                    'card' => $card,
                    'key' => $key,
                    'deck' => $this->deck($tags[Row::int($row, 'id')]),
                    'note_id' => Row::int($row, 'id'),
                    'note_title' => Row::string($row, 'title'),
                    'note_path' => $path,
                    'state' => $states[$key] ?? null,
                ];
            }
        }

        return array_values($found);
    }

    /**
     * A note's deck: its most specific flashcards tag, e.g. `#flashcards/spanish/verbs` is
     * "spanish/verbs"; plain `#flashcards` is "General".
     *
     * @param  list<string>  $tags
     */
    private function deck(array $tags): string
    {
        usort($tags, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $deck = mb_substr($tags[0], mb_strlen(self::TAG) + 1);

        return $deck === '' ? 'General' : $deck;
    }

    /**
     * @param  list<FoundCard>  $found
     * @return array<string, list<FoundCard>>
     */
    private function byDeck(array $found): array
    {
        $decks = [];

        foreach ($found as $card) {
            $decks[$card['deck']][] = $card;
        }

        ksort($decks, SORT_NATURAL | SORT_FLAG_CASE);

        return $decks;
    }

    /**
     * @param  FoundCard  $card
     * @return array<string, mixed>
     */
    private function present(Vault $vault, array $card, string $today): array
    {
        return [
            'key' => $card['key'],
            'kind' => $card['card']->kind,
            'deck' => $card['deck'],
            'front' => $this->renderMarkdown->handle($vault, $card['card']->front, $card['note_path']),
            'back' => $this->renderMarkdown->handle($vault, $card['card']->back, $card['note_path']),
            'note' => ['id' => $card['note_id'], 'title' => $card['note_title'], 'line' => $card['card']->line],
            'is_new' => $card['state'] === null,
            'intervals' => $this->scheduler->preview($card['state'], $today),
        ];
    }
}
