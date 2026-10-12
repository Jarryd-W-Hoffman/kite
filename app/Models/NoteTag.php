<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NoteTagFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $vault_id
 * @property-read int $note_id
 * @property-read string $name
 * @property-read Note $note
 */
#[WithoutTimestamps]
final class NoteTag extends Model
{
    /** @use HasFactory<NoteTagFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'vault_id' => 'integer',
            'note_id' => 'integer',
            'name' => 'string',
        ];
    }

    /**
     * @return BelongsTo<Note, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
