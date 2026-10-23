<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SystemOpener;
use Illuminate\Http\Response;

final readonly class DiagnosticsLogController
{
    /**
     * Show the log files in the system file manager (desktop app only).
     */
    public function store(SystemOpener $opener): Response
    {
        abort_unless((bool) config('nativephp-internal.running'), 404);

        $opener->open(storage_path('logs'));

        return response()->noContent();
    }
}
