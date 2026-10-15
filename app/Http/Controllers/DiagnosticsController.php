<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CollectDiagnostics;
use Illuminate\Http\JsonResponse;

final readonly class DiagnosticsController
{
    /**
     * The diagnostics report, for the user to copy into a bug report.
     */
    public function show(CollectDiagnostics $action): JsonResponse
    {
        return response()->json(['report' => $action->handle()]);
    }
}
