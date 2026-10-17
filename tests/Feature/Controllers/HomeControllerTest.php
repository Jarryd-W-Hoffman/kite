<?php

declare(strict_types=1);

use App\Models\Vault;
use App\Services\Settings;

beforeEach(function (): void {
    config(['nativephp.version' => '0.2.0']);
});

it('sends first-time users to the vault picker', function (): void {
    $this->get(route('home'))->assertRedirectToRoute('vaults.index');

    expect(resolve(Settings::class)->state('last_version'))->toBe('0.2.0');
});

it('re-opens the most recently opened vault on its Welcome tab', function (): void {
    Vault::factory()->create(['opened_at' => now()->subDay()]);
    $recent = Vault::factory()->create(['opened_at' => now()]);
    resolve(Settings::class)->rememberState('last_version', '0.2.0');

    $this->get(route('home'))->assertRedirectToRoute('welcome.show', $recent);
});

it('re-opens the vault itself when the Welcome tab is off at startup', function (): void {
    $vault = Vault::factory()->create(['opened_at' => now()]);
    resolve(Settings::class)->update(['welcome_on_startup' => false]);
    resolve(Settings::class)->rememberState('last_version', '0.2.0');

    $this->get(route('home'))->assertRedirectToRoute('vaults.show', $vault);
});

it("shows what's new after an update, even with the Welcome tab off at startup", function (): void {
    $vault = Vault::factory()->create(['opened_at' => now()]);
    resolve(Settings::class)->update(['welcome_on_startup' => false]);
    resolve(Settings::class)->rememberState('last_version', '0.1.1');

    $this->get(route('home'))->assertRedirectToRoute('welcome.show', [$vault, 'updated' => 1]);

    expect(resolve(Settings::class)->state('last_version'))->toBe('0.2.0');
});
