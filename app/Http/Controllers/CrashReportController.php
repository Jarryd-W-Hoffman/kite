<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCrashReportRequest;
use App\Services\CrashReports;
use Illuminate\Http\Response;

final readonly class CrashReportController
{
    /**
     * An error from the interface. It's only passed on when the user turned crash reports on.
     */
    public function store(StoreCrashReportRequest $request, CrashReports $reports): Response
    {
        $reports->reportInterfaceError(
            $request->string('message')->toString(),
            $request->filled('stack') ? $request->string('stack')->toString() : null,
        );

        return response()->noContent();
    }
}
