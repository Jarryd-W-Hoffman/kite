<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;
use Native\Desktop\Facades\AutoUpdater;
use Throwable;

final readonly class AppUpdateController
{
    /**
     * Quit and install a downloaded update now, not when Kite next quits (desktop app only).
     */
    public function store(): Response
    {
        abort_unless((bool) config('nativephp-internal.running'), 404);

        // Electron quits to install before it answers, so the request is cut off: that's success.
        // Anything else still fails.
        rescue(
            AutoUpdater::quitAndInstall(...),
            fn (Throwable $exception): null => $exception instanceof ConnectionException ? null : throw $exception,
            report: false,
        );

        return response()->noContent();
    }
}
