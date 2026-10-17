<?php

declare(strict_types=1);

use App\Actions\RecordLaunch;
use App\Enums\WelcomeReason;
use App\Models\Vault;
use App\Services\Settings;

beforeEach(function (): void {
    config(['nativephp.version' => '0.2.0']);
});

it('remembers the version it started as', function (): void {
    resolve(RecordLaunch::class)->handle();

    expect(resolve(Settings::class)->state('last_version'))->toBe('0.2.0');
});

it('opens the Welcome tab on a fresh start, as it is on by default', function (): void {
    expect(resolve(RecordLaunch::class)->handle())->toBe(WelcomeReason::Startup);
});

it('opens it for an update, a version newer than the last one', function (string $previous, ?WelcomeReason $reason): void {
    resolve(Settings::class)->rememberState('last_version', $previous);
    resolve(Settings::class)->update(['welcome_on_startup' => false]);

    expect(resolve(RecordLaunch::class)->handle())->toBe($reason);
})->with([
    'newer' => ['0.1.9', WelcomeReason::Updated],
    'same' => ['0.2.0', null],
    'older (downgraded)' => ['0.3.0', null],
]);

it('treats a vault without a recorded version as an update from before the Welcome tab', function (): void {
    Vault::factory()->create();

    expect(resolve(RecordLaunch::class)->handle())->toBe(WelcomeReason::Updated);
});

it("skips what's new when that's turned off", function (): void {
    resolve(Settings::class)->rememberState('last_version', '0.1.0');
    resolve(Settings::class)->update(['whats_new_after_update' => false, 'welcome_on_startup' => false]);

    expect(resolve(RecordLaunch::class)->handle())->toBeNull();
});
