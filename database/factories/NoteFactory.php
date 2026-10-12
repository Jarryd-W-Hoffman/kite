<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Note;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
final class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = mb_rtrim(fake()->unique()->sentence(3), '.');

        return [
            'vault_id' => Vault::factory(),
            'path' => $title.'.md',
            'path_key' => mb_strtolower($title),
            'slug' => mb_strtolower($title),
            'title' => $title,
            'frontmatter' => [],
            'word_count' => 0,
            'size' => 0,
            'modified_at' => 0,
            'hash' => hash('xxh128', $title),
        ];
    }
}
