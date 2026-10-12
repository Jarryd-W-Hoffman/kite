<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\Vault;

it('sends first-time users to the vault picker', function (): void {
    $this->get(route('home'))->assertRedirectToRoute('vaults.index');
});

it('re-opens the most recently opened vault', function (): void {
    Vault::factory()->create(['opened_at' => now()->subDay()]);
    $recent = Vault::factory()->create(['opened_at' => now()]);

    $this->get(route('home'))->assertRedirectToRoute('vaults.show', $recent);
});

it('lists known vaults', function (): void {
    $vault = vaultWithFiles(['A.md' => 'a']);
    resolve(IndexVault::class)->handle($vault);

    $this->get(route('vaults.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vault/index')
            ->where('canPickFolder', false)
            ->has('vaults', 1)
            ->where('vaults.0.id', $vault->id)
            ->where('vaults.0.notes_count', 1)
            ->where('vaults.0.opened_at', $vault->opened_at?->toIso8601String()));
});

it('opens a folder as a vault', function (): void {
    $folder = vaultWithFiles(['Home.md' => '# Home']);
    $folder->delete();

    $response = $this->post(route('vaults.store'), ['path' => $folder->path]);

    $vault = Vault::query()->sole();

    $response->assertRedirectToRoute('vaults.show', $vault);

    expect($vault->notes()->count())->toBe(1);
});

it('validates the folder path', function (array $data): void {
    $this->fromRoute('vaults.index')
        ->post(route('vaults.store'), $data)
        ->assertRedirectToRoute('vaults.index')
        ->assertSessionHasErrors('path');
})->with([
    'missing' => [[]],
    'not a folder' => [['path' => '/no/such/kite/folder']],
]);

it('lands on the home note of a vault', function (): void {
    $vault = vaultWithFiles([
        'Recent.md' => 'recent',
        'README.md' => '# Readme',
        'Sub/Home.md' => 'nested home',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $this->travel(1)->hour();

    $this->get(route('vaults.show', $vault))
        ->assertRedirectToRoute('notes.show', [$vault, Note::query()->where('path', 'README.md')->sole()]);

    expect($vault->refresh()->opened_at?->toIso8601String())->toBe(now()->toIso8601String());
});

it('falls back to the most recently modified note', function (): void {
    $vault = vaultWithFiles(['Old.md' => 'old', 'New.md' => 'new']);
    touch($vault->path.'/Old.md', time() - 100);
    resolve(IndexVault::class)->handle($vault);

    $this->get(route('vaults.show', $vault))
        ->assertRedirectToRoute('notes.show', [$vault, Note::query()->where('path', 'New.md')->sole()]);
});

it('shows an empty workspace for an empty vault', function (): void {
    $vault = vaultWithFiles();

    $this->get(route('vaults.show', $vault))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('note/show')
            ->where('vault.id', $vault->id)
            ->where('note', null)
            ->has('tree', 0));
});

it('forgets a vault without touching its files', function (): void {
    $vault = vaultWithFiles(['Keep.md' => 'keep']);
    resolve(IndexVault::class)->handle($vault);

    $this->delete(route('vaults.destroy', $vault))
        ->assertRedirectToRoute('vaults.index')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Vault removed from Kite. Your files were not touched.')]);

    expect(Vault::query()->count())->toBe(0)
        ->and(Note::query()->count())->toBe(0)
        ->and(file_exists($vault->path.'/Keep.md'))->toBeTrue();
});
