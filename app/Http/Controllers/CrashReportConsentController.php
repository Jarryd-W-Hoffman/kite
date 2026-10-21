<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCrashReportConsentRequest;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;

final readonly class CrashReportConsentController
{
    /**
     * The user's answer to "Send crash reports?", asked once on the Welcome tab.
     */
    public function update(UpdateCrashReportConsentRequest $request, Settings $settings): RedirectResponse
    {
        $settings->update(['crash_reports' => $request->boolean('crash_reports')]);
        $settings->rememberState('crash_reports_asked', true);

        return back();
    }
}
