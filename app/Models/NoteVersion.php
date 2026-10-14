<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VersionSource;
use Carbon\CarbonInterface;
use Database\Factories\NoteVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A snapshot of a note's text.
 *
 * @property-read int $id
 * @property-read int $note_id
 * @property-read string $contents
 * @property-read string $hash
 * @property-read VersionSource $source
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Note $note
 */
final class NoteVersion extends Model
{
    /** @use HasFactory<NoteVersionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'note_id' => 'integer',
            'contents' => 'string',
            'hash' => 'string',
            'source' => VersionSource::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Note, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    /**
     * @return array{id: int, source: string, size: int, saved_at: string}
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'size' => mb_strlen($this->contents),
            // Folded saves move a version forward, so the last save is what matters.
            'saved_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
