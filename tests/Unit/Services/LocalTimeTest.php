<?php

declare(strict_types=1);

use App\Services\LocalTime;
use App\Services\Templates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

function withTimezoneHeader(?string $timezone): void
{
    $request = Request::create('/');

    if ($timezone !== null) {
        $request->headers->set(LocalTime::HEADER, $timezone);
    }

    app()->instance('request', $request);
}

it("uses the timezone the window sends, or the app's when it sends none or a made-up one", function (?string $header, string $timezone): void {
    withTimezoneHeader($header);

    expect(resolve(LocalTime::class)->timezone())->toBe($timezone)
        ->and(resolve(LocalTime::class)->now()->getTimezone()->getName())->toBe($timezone);
})->with([
    'a real timezone' => ['Australia/Brisbane', 'Australia/Brisbane'],
    'none' => [null, 'UTC'],
    'a made-up one' => ['Mars/Olympus_Mons', 'UTC'],
]);

it('fills in template dates and times in local time', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-18 20:30:00', 'UTC'));
    withTimezoneHeader('Australia/Brisbane');

    expect(resolve(Templates::class)->render('{{date}} {{time}} {{yesterday}}', ['title' => 'x']))
        ->toBe('2026-10-19 06:30 2026-10-18');
});
