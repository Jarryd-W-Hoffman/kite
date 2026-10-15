<?php

declare(strict_types=1);

use App\Services\Flashcards\Scheduler;

beforeEach(fn (): Scheduler => $this->scheduler = new Scheduler());

it('schedules a new card by how well it was known', function (string $grade, int $interval, string $due, float $ease): void {
    expect($this->scheduler->review(null, $grade, '2026-10-16'))->toBe([
        'due' => $due,
        'interval' => $interval,
        'ease' => $ease,
        'reps' => $grade === 'again' ? 0 : 1,
        'lapses' => 0,
        'introduced' => '2026-10-16',
        'reviewed' => '2026-10-16',
    ]);
})->with([
    'again' => ['again', 0, '2026-10-16', 2.5],
    'hard' => ['hard', 1, '2026-10-17', 2.35],
    'good' => ['good', 1, '2026-10-17', 2.5],
    'easy' => ['easy', 4, '2026-10-20', 2.65],
]);

it('stretches the interval of a card it knows, and starts over one it forgot', function (): void {
    $state = ['due' => '2026-10-16', 'interval' => 10, 'ease' => 2.5, 'reps' => 3, 'lapses' => 0, 'introduced' => '2026-09-01', 'reviewed' => '2026-10-06'];

    expect($this->scheduler->review($state, 'good', '2026-10-16'))->toMatchArray(['interval' => 25, 'due' => '2026-11-10', 'reps' => 4, 'introduced' => '2026-09-01'])
        ->and($this->scheduler->review($state, 'hard', '2026-10-16'))->toMatchArray(['interval' => 12, 'ease' => 2.35])
        ->and($this->scheduler->review($state, 'easy', '2026-10-16'))->toMatchArray(['interval' => 33, 'ease' => 2.65])
        ->and($this->scheduler->review($state, 'again', '2026-10-16'))->toMatchArray(['interval' => 0, 'due' => '2026-10-16', 'ease' => 2.3, 'reps' => 0, 'lapses' => 1]);
});

it('never lets a card get harder than the floor', function (): void {
    $state = ['due' => '2026-10-16', 'interval' => 2, 'ease' => 1.35, 'reps' => 5, 'lapses' => 4, 'introduced' => '2026-09-01', 'reviewed' => '2026-10-14'];

    expect($this->scheduler->review($state, 'again', '2026-10-16')['ease'])->toBe(1.3)
        ->and($this->scheduler->review($state, 'good', '2026-10-16')['interval'])->toBe(3);
});

it('previews where each grade would put a card', function (): void {
    expect($this->scheduler->preview(null, '2026-10-16'))->toBe(['again' => 0, 'hard' => 1, 'good' => 1, 'easy' => 4]);
});

it('refuses unknown grades', function (): void {
    $this->scheduler->review(null, 'perfect', '2026-10-16');
})->throws(InvalidArgumentException::class);
