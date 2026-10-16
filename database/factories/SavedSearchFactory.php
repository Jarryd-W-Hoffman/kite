<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
final class SavedSearchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vault_id' => Vault::factory(),
            'name' => ucfirst(fake()->word()),
            'query' => fake()->word(),
        ];
    }
}
