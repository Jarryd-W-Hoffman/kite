<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\NoteCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment on a passage of a note. The passage is stored as its text plus a little context on
 * each side, so it can be found again after the note is edited, in Kite or anywhere else.
 *
 * @property-read int $id
 * @property-read int $note_id
 * @property-read string $body
 * @property-read string $quote
 * @property-read string $prefix
 * @property-read string $suffix
 * @property-read CarbonInterface|null $resolved_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Note $note
 */
final class NoteComment extends Model
{
    /** @use HasFactory<NoteCommentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'note_id' => 'integer',
            'body' => 'string',
            'quote' => 'string',
            'prefix' => 'string',
            'suffix' => 'string',
            'resolved_at' => 'datetime',
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
     * @return array{id: int, body: string, quote: string, prefix: string, suffix: string, resolved: bool, created_at: string, updated_at: string}
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'quote' => $this->quote,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'resolved' => $this->resolved_at !== null,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
