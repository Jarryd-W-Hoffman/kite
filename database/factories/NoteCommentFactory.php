<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Note;
use App\Models\NoteComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteComment>
 */
final class NoteCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'note_id' => Note::factory(),
            'body' => fake()->sentence(),
            'quote' => fake()->words(3, true),
            'prefix' => '',
            'suffix' => '',
            'resolved_at' => null,
        ];
    }

    public function resolved(): self
    {
        return $this->state(fn (): array => ['resolved_at' => now()]);
    }
}
