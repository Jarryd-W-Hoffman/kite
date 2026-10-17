<?php

declare(strict_types=1);

use App\Models\Vault;
use App\Services\Settings;
use Inertia\Testing\AssertableInertia;

it('shows the Welcome tab inside the vault', function (): void {
    config(['nativephp.version' => '0.2.0', 'nativephp.website' => 'https://github.com/Jarryd-W-Hoffman/kite']);
    $vault = vaultWithFiles();
    $vault->update(['opened_at' => now()->subDay()]);

    $this->get(route('welcome.show', [$vault, 'updated' => 1]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('welcome/show')
            ->where('vault.id', $vault->id)
            ->where('version', '0.2.0')
            ->where('updated', true)
            ->where('release_notes_url', 'https://github.com/Jarryd-W-Hoffman/kite/releases/tag/v0.2.0')
            ->where('welcome_on_startup', true));

    expect($vault->fresh()?->opened_at?->isToday())->toBeTrue();
});

it('says it was not just updated without the flag', function (): void {
    $vault = vaultWithFiles();

    $this->get(route('welcome.show', $vault))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('updated', false));
});

it('turns the Welcome tab off and on at startup from its checkbox', function (): void {
    $vault = Vault::factory()->create();

    $this->from(route('welcome.show', $vault))
        ->put(route('welcome.update'), ['welcome_on_startup' => false])
        ->assertRedirect(route('welcome.show', $vault));

    expect(resolve(Settings::class)->all()['welcome_on_startup'])->toBeFalse();

    $this->put(route('welcome.update'), ['welcome_on_startup' => true]);

    expect(resolve(Settings::class)->all()['welcome_on_startup'])->toBeTrue();
});

it('validates the checkbox', function (): void {
    $this->put(route('welcome.update'), ['welcome_on_startup' => 'maybe'])
        ->assertSessionHasErrors('welcome_on_startup');
});
