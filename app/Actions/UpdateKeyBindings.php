<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\AppMenu;
use App\Services\KeyBindings;
use App\Services\Settings;

final readonly class UpdateKeyBindings
{
    public function __construct(
        private Settings $settings,
        private KeyBindings $bindings,
        private AppMenu $menu,
    ) {
        //
    }

    /**
     * Change commands' shortcuts: an accelerator, an empty string for none, or null for the
     * default. The app menu, which owns the shortcuts in the desktop app, is rebuilt to match.
     *
     * @param  array<string, string|null>  $changes
     */
    public function handle(array $changes): void
    {
        $overrides = $this->bindings->overrides();
        $defaults = $this->bindings->defaults();

        foreach ($changes as $command => $accelerator) {
            if ($accelerator === null || $accelerator === $defaults[$command]) {
                unset($overrides[$command]);
            } else {
                $overrides[$command] = $accelerator;
            }
        }

        $this->settings->update(['key_bindings' => $overrides]);

        if (config('nativephp-internal.running')) {
            $this->menu->register();
        }
    }
}
