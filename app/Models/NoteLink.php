<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NoteLinkFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $vault_id
 * @property-read int $source_note_id
 * @property-read int|null $target_note_id
 * @property-read string $target
 * @property-read string $target_key
 * @property-read string|null $heading
 * @property-read string|null $alias
 * @property-read bool $is_embed
 * @property-read int $line
 * @property-read Note $source
 * @property-read Note|null $targetNote
 */
#[WithoutTimestamps]
final class NoteLink extends Model
{
    /** @use HasFactory<NoteLinkFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'vault_id' => 'integer',
            'source_note_id' => 'integer',
            'target_note_id' => 'integer',
            'target' => 'string',
            'target_key' => 'string',
            'heading' => 'string',
            'alias' => 'string',
            'is_embed' => 'boolean',
            'line' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Note, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'source_note_id');
    }

    /**
     * @return BelongsTo<Note, $this>
     */
    public function targetNote(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'target_note_id');
    }
}
