<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\QuickCaptureRequested;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Native\Desktop\Facades\GlobalShortcut;

final readonly class PreferencesController
{
    public function update(UpdatePreferencesRequest $request, Settings $settings): RedirectResponse
    {
        $previousShortcut = $settings->all()['capture_shortcut'];

        $settings->update($request->validated());

        $shortcut = $settings->all()['capture_shortcut'];

        if (config('nativephp-internal.running') && $shortcut !== $previousShortcut) {
            if (is_string($previousShortcut) && $previousShortcut !== '') {
                GlobalShortcut::key($previousShortcut)->unregister();
            }

            if (is_string($shortcut) && $shortcut !== '') {
                GlobalShortcut::key($shortcut)->event(QuickCaptureRequested::class)->register();
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preferences saved.')]);

        return back();
    }
}
