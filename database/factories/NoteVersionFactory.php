<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VersionSource;
use App\Models\Note;
use App\Models\NoteVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteVersion>
 */
final class NoteVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contents = fake()->paragraph();

        return [
            'note_id' => Note::factory(),
            'contents' => $contents,
            'hash' => hash('sha1', $contents),
            'source' => VersionSource::Edit,
        ];
    }

    public function contents(string $contents): self
    {
        return $this->state(fn (): array => ['contents' => $contents, 'hash' => hash('sha1', $contents)]);
    }
}
