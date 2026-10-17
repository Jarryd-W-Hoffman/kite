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

        return Inertia::render('welcome/show', [
            ...$workspace->handle($vault),
            'version' => $version,
            'updated' => $request->boolean('updated'),
            'whats_new' => $changelog->html($version),
            'release_notes_url' => mb_rtrim(config()->string('nativephp.website'), '/')."/releases/tag/v{$version}",
            'welcome_on_startup' => $settings->all()['welcome_on_startup'] === true,
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
