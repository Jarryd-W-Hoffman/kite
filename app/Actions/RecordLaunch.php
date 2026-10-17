<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\WelcomeReason;
use App\Models\Vault;
use App\Services\Settings;

final readonly class RecordLaunch
{
    public function __construct(private Settings $settings)
    {
        //
    }

    /**
     * Note the version Kite is starting as, and decide whether the Welcome tab should open: after
     * an update (unless what's new is turned off), or on every start when the user wants it.
     */
    public function handle(): ?WelcomeReason
    {
        $version = config()->string('nativephp.version');
        $previous = $this->settings->state('last_version');
        $preferences = $this->settings->all();

        $this->settings->rememberState('last_version', $version);

        // Versions before the Welcome tab didn't record themselves, so a vault without a recorded
        // version means Kite was used before this update.
        $updated = is_string($previous)
            ? version_compare($version, $previous, '>')
            : Vault::query()->exists();

        if ($updated && $preferences['whats_new_after_update'] === true) {
            return WelcomeReason::Updated;
        }

        return $preferences['welcome_on_startup'] === true ? WelcomeReason::Startup : null;
    }
}
