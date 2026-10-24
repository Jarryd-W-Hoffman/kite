<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PendingLinkRewrite;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PendingLinkRewrite>
 */
final class PendingLinkRewriteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'vault_id' => Vault::factory(),
            'keys' => [$name],
            'path' => ucfirst($name).' renamed.md',
        ];
    }
}
