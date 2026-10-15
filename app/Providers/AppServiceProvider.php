<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\QuickCaptureRequested;
use App\Listeners\OpenQuickCapture;
use App\Models\ColourGroup;
use App\Models\Note;
use App\Models\Vault;
use App\Services\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->booted(fn () => resolve(Settings::class)->applyToConfig());

        // Registered explicitly: the listener ignores its payload, so discovery can't infer the event.
        Event::listen(QuickCaptureRequested::class, OpenQuickCapture::class);

        // Changes the workspace sidebar shows move the vault to a new tree revision.
        Note::updated(function (Note $note): void {
            if ($note->wasChanged(Note::TREE_FIELDS)) {
                Vault::markTreeChanged($note->vault_id);
            }
        });
        Note::deleted(fn (Note $note) => Vault::markTreeChanged($note->vault_id));
        ColourGroup::saved(fn (ColourGroup $group) => Vault::markTreeChanged($group->vault_id));
        ColourGroup::deleted(fn (ColourGroup $group) => Vault::markTreeChanged($group->vault_id));
    }
}
