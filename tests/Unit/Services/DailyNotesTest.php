<?php

declare(strict_types=1);

use App\Services\DailyNotes;
use Carbon\CarbonImmutable;

it('maps dates to daily note paths and back', function (): void {
    $daily = new DailyNotes();
    $date = CarbonImmutable::parse('2026-10-13');

    expect($daily->pathFor($date))->toBe('Daily/2026-10-13.md')
        ->and($daily->dateOf('Daily/2026-10-13.md')?->toDateString())->toBe('2026-10-13')
        ->and($daily->dateOf('Daily/2026-02-31.md'))->toBeNull()
        ->and($daily->dateOf('Daily/notes.md'))->toBeNull()
        ->and($daily->dateOf('Other/2026-10-13.md'))->toBeNull()
        ->and($daily->dateOf('Daily/2026-10-13.txt'))->toBeNull()
        ->and($daily->template())->toBeNull();
});

it('honours custom folders, formats and templates', function (): void {
    config(['kite.daily.folder' => '/', 'kite.daily.format' => 'd.m.Y', 'kite.daily.template' => ' Templates/Day.md ']);

    $daily = new DailyNotes();

    expect($daily->pathFor(CarbonImmutable::parse('2026-10-13')))->toBe('13.10.2026.md')
        ->and($daily->dateOf('13.10.2026.md')?->toDateString())->toBe('2026-10-13')
        ->and($daily->template())->toBe('Templates/Day.md');
});

it('falls back to defaults for invalid config', function (): void {
    config(['kite.daily.folder' => null, 'kite.daily.format' => '', 'kite.daily.template' => '  ']);

    $daily = new DailyNotes();

    expect($daily->pathFor(CarbonImmutable::parse('2026-01-02')))->toBe('Daily/2026-01-02.md')
        ->and($daily->template())->toBeNull();
});
