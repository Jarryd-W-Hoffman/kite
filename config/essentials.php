<?php

declare(strict_types=1);

use NunoMaduro\Essentials\Configurables\ForceScheme;
use NunoMaduro\Essentials\Configurables\Unguard;

return [
    Unguard::class => true,

    // The desktop app serves itself over plain http on localhost, so forcing https in production
    // pointed every redirect and asset at an address nothing answers, leaving a blank window.
    ForceScheme::class => false,
];
