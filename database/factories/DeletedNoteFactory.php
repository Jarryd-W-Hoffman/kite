<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeletedNote;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeletedNote>
 */
final class DeletedNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = mb_rtrim(fake()->sentence(3), '.');

        return [
            'vault_id' => Vault::factory(),
            'path' => $title.'.md',
            'title' => $title,
            'hash' => hash('xxh128', $title),
            'size' => fake()->numberBetween(1, 5000),
        ];
    }
}
