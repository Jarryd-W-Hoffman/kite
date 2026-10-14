<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The palette colour groups can use. Each maps to a --group-* CSS variable tuned for light and
 * dark themes, so stored groups stay readable whichever theme is active.
 */
enum GroupColour: string
{
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Lime = 'lime';
    case Green = 'green';
    case Teal = 'teal';
    case Sky = 'sky';
    case Blue = 'blue';
    case Violet = 'violet';
    case Pink = 'pink';
}
