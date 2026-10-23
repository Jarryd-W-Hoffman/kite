<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LoadWorkspace;
use App\Http\Requests\UpdateWelcomeRequest;
use App\Models\Vault;
use App\Services\Changelog;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WelcomeController
{
    /**
     * The Welcome tab: what's new in this version, quick actions, recent notes and tips.
     */
    public function show(Request $request, Vault $vault, LoadWorkspace $workspace, Settings $settings, Changelog $changelog): Response
    {
        $vault->update(['opened_at' => now()]);

        $version = config()->string('nativephp.version');
        $preferences = $settings->all();

        return Inertia::render('welcome/show', [
            ...$workspace->handle($vault),
            'version' => $version,
            'updated' => $request->boolean('updated'),
            'whats_new' => $changelog->html($version),
            'release_notes_url' => mb_rtrim(config()->string('nativephp.website'), '/')."/releases/tag/v{$version}",
            'welcome_on_startup' => $preferences['welcome_on_startup'] === true,
            // Empty when quick capture is turned off.
            'capture_shortcut' => is_string($preferences['capture_shortcut']) ? $preferences['capture_shortcut'] : '',
            // Asked once, and only when there's somewhere to send reports.
            'ask_crash_reports' => $settings->state('crash_reports_asked') !== true && filled(config('sentry.dsn')),
        ]);
    }

    /**
     * Turn the Welcome tab on or off at startup, from its own checkbox.
     */
    public function update(UpdateWelcomeRequest $request, Settings $settings): RedirectResponse
    {
        $settings->update(['welcome_on_startup' => $request->boolean('welcome_on_startup')]);

        return back();
    }
}
