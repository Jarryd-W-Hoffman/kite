<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Native\Desktop\Facades\AutoUpdater;

final readonly class AppUpdateController
{
    /**
     * Quit and install a downloaded update now, not when Kite next quits (desktop app only).
     */
    public function store(): Response
    {
        abort_unless((bool) config('nativephp-internal.running'), 404);

        AutoUpdater::quitAndInstall();

        return response()->noContent();
    }
}
