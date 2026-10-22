<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * The time where the person using Kite is, for dates and times written into their notes (today's
 * daily note, a capture's time, a template's {{date}}). Kite stores its own timestamps in UTC; the
 * window says which timezone it's in with each request, so notes get the local time.
 */
final readonly class LocalTime
{
    public const string HEADER = 'X-Kite-Timezone';

    /**
     * The window's timezone, when it sent a valid one, or the app's.
     */
    public function timezone(): string
    {
        $timezone = request()->header(self::HEADER);

        return is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : config()->string('app.timezone');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }
}
