<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GroupColour;
use App\Models\ColourGroup;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColourGroup>
 */
final class ColourGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tag = fake()->unique()->word();

        return [
            'vault_id' => Vault::factory(),
            'name' => ucfirst($tag),
            'query' => 'tag:'.$tag,
            'colour' => fake()->randomElement(GroupColour::cases()),
            'position' => 0,
        ];
    }
}
