<?php

declare(strict_types=1);

use App\Models\Vault;

it('sends first-time users to the vault picker', function (): void {
    $this->get(route('home'))->assertRedirectToRoute('vaults.index');
});

it('re-opens the most recently opened vault', function (): void {
    Vault::factory()->create(['opened_at' => now()->subDay()]);
    $recent = Vault::factory()->create(['opened_at' => now()]);

    $this->get(route('home'))->assertRedirectToRoute('vaults.show', $recent);
});
