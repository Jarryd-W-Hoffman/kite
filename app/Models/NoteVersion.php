<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CompressedText;
use App\Enums\VersionSource;
use Carbon\CarbonInterface;
use Database\Factories\NoteVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A snapshot of a note's text, stored compressed. `size` is the text's length in characters, so
 * a note's history can be listed without reading every version.
 *
 * @property-read int $id
 * @property-read int $note_id
 * @property-read string $contents
 * @property-read string $hash
 * @property-read int $size
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
            'contents' => CompressedText::class.':size',
            'hash' => 'string',
            'size' => 'integer',
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
            'size' => $this->size,
            // Folded saves move a version forward, so the last save is what matters.
            'saved_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
