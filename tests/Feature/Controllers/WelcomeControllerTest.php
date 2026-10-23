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
            ->where('welcome_on_startup', true)
            ->has('whats_new'));

    expect($vault->fresh()?->opened_at?->isToday())->toBeTrue();
});

it("passes the quick capture shortcut set in Preferences, or none when it's off", function (?string $shortcut, string $shared): void {
    $vault = vaultWithFiles();

    if ($shortcut !== 'default') {
        resolve(Settings::class)->update(['capture_shortcut' => $shortcut]);
    }

    $this->get(route('welcome.show', $vault))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('capture_shortcut', $shared));
})->with([
    'the default' => ['default', 'CmdOrCtrl+Shift+Space'],
    'a changed one' => ['Alt+Shift+K', 'Alt+Shift+K'],
    'off' => [null, ''],
]);

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

it("passes this version's changelog entry as HTML", function (): void {
    $path = storage_path('framework/testing/changelog-welcome.md');
    file_put_contents($path, "## [0.2.0]\n\n- **New** things\n");
    config(['nativephp.version' => '0.2.0', 'kite.changelog' => $path]);

    $this->get(route('welcome.show', vaultWithFiles()))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('whats_new', "<ul>\n<li><strong>New</strong> things</li>\n</ul>\n"));

    config(['nativephp.version' => '0.3.0']);

    $this->get(route('welcome.show', vaultWithFiles()))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('whats_new', null));

    unlink($path);
});
