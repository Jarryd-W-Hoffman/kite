<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a note version came about.
 */
enum VersionSource: string
{
    /** Saved from Kite's editor. */
    case Edit = 'edit';

    /** Found on disk, changed by another app (or the text before Kite first saved it). */
    case External = 'external';

    /** Written back from an older version. */
    case Restore = 'restore';
}
