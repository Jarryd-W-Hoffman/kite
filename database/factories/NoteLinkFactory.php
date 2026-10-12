<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteLink>
 */
final class NoteLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $target = mb_rtrim(fake()->unique()->sentence(3), '.');

        return [
            'vault_id' => Vault::factory(),
            'source_note_id' => Note::factory(),
            'target_note_id' => null,
            'target' => $target,
            'target_key' => mb_strtolower($target),
            'heading' => null,
            'alias' => null,
            'is_embed' => false,
            'line' => 1,
        ];
    }
}
