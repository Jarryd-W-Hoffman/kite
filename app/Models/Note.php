<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read int $vault_id
 * @property-read string $path
 * @property-read string $path_key
 * @property-read string $slug
 * @property-read string $title
 * @property-read array<string, mixed> $frontmatter
 * @property-read int $word_count
 * @property-read bool $locked
 * @property-read int $size
 * @property-read int $modified_at
 * @property-read string $hash
 * @property-read CarbonInterface|null $starred_at
 * @property-read CarbonInterface|null $opened_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Vault $vault
 */
final class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /**
     * Fields the workspace sidebar shows or groups notes by.
     */
    public const array TREE_FIELDS = ['path', 'title', 'frontmatter', 'locked'];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'vault_id' => 'integer',
            'path' => 'string',
            'path_key' => 'string',
            'slug' => 'string',
            'title' => 'string',
            'frontmatter' => 'array',
            'word_count' => 'integer',
            'locked' => 'boolean',
            'size' => 'integer',
            'modified_at' => 'integer',
            'hash' => 'string',
            'starred_at' => 'datetime',
            'opened_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Vault, $this>
     */
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    /**
     * @return HasMany<NoteLink, $this>
     */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(NoteLink::class, 'source_note_id');
    }

    /**
     * @return HasMany<NoteVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(NoteVersion::class);
    }

    /**
     * @return HasMany<NoteLink, $this>
     */
    public function backlinks(): HasMany
    {
        return $this->hasMany(NoteLink::class, 'target_note_id');
    }

    /**
     * @return HasMany<NoteTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(NoteTag::class);
    }
}
