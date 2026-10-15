<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Native\Desktop\Facades\Shell;

final readonly class DiagnosticsLogController
{
    /**
     * Show the log files in the system file manager (desktop app only).
     */
    public function store(): Response
    {
        abort_unless((bool) config('nativephp-internal.running'), 404);

        Shell::openFile(storage_path('logs'));

        return response()->noContent();
    }
}
