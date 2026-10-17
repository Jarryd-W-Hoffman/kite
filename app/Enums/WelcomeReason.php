<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why Kite opens the Welcome tab when it starts.
 */
enum WelcomeReason: string
{
    /** Kite has been updated since it last ran, so the tab shows what's new. */
    case Updated = 'updated';

    /** The user keeps the Welcome tab on at startup. */
    case Startup = 'startup';
}
