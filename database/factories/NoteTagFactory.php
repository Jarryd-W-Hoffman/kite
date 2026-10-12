<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Note;
use App\Models\NoteTag;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteTag>
 */
final class NoteTagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vault_id' => Vault::factory(),
            'note_id' => Note::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
