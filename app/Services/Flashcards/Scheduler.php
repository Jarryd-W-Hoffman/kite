<?php

declare(strict_types=1);

namespace App\Services\Flashcards;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * When to show a card next, by the SM-2 method Anki is built on. Each card has an interval (days
 * until it's due) and an ease (how fast the interval grows). Remembering it well stretches the
 * interval; forgetting it starts it over and makes it grow more slowly from then on. Dates are
 * the reviewer's local days.
 *
 * @phpstan-type CardState array{due: string, interval: int, ease: float, reps: int, lapses: int, introduced: string, reviewed: string}
 */
final readonly class Scheduler
{
    public const array GRADES = ['again', 'hard', 'good', 'easy'];

    private const float START_EASE = 2.5;

    private const float MIN_EASE = 1.3;

    /**
     * The card's state after a review on `$today` (Y-m-d).
     *
     * @param  CardState|null  $state  Null for a card never reviewed.
     * @return CardState
     */
    public function review(?array $state, string $grade, string $today): array
    {
        throw_unless(in_array($grade, self::GRADES, true), InvalidArgumentException::class, "Unknown grade [{$grade}].");

        $interval = $state['interval'] ?? 0;
        $ease = $state['ease'] ?? self::START_EASE;
        $lapses = $state['lapses'] ?? 0;
        $isNew = $state === null || $state['reps'] === 0;

        [$interval, $ease, $lapses] = match ($grade) {
            // Forgotten: see it again today, and grow its interval more slowly from now on.
            'again' => [0, $isNew ? $ease : max(self::MIN_EASE, $ease - 0.2), $isNew ? $lapses : $lapses + 1],
            'hard' => [$isNew ? 1 : max(1, (int) round($interval * 1.2)), max(self::MIN_EASE, $ease - 0.15), $lapses],
            'good' => [$isNew ? 1 : max($interval + 1, (int) round($interval * $ease)), $ease, $lapses],
            default => [$isNew ? 4 : max($interval + 1, (int) round($interval * $ease * 1.3)), $ease + 0.15, $lapses],
        };

        return [
            'due' => CarbonImmutable::parse($today)->addDays($interval)->toDateString(),
            'interval' => $interval,
            'ease' => round($ease, 2),
            'reps' => $grade === 'again' ? 0 : ($state['reps'] ?? 0) + 1,
            'lapses' => $lapses,
            'introduced' => $state['introduced'] ?? $today,
            'reviewed' => $today,
        ];
    }

    /**
     * How far off each grade would put the card, for the buttons: e.g. ['again' => 0, 'good' => 3].
     *
     * @param  CardState|null  $state
     * @return array<string, int>
     */
    public function preview(?array $state, string $today): array
    {
        $intervals = [];

        foreach (self::GRADES as $grade) {
            $intervals[$grade] = $this->review($state, $grade, $today)['interval'];
        }

        return $intervals;
    }
}
