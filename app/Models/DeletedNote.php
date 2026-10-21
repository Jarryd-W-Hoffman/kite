<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DeletedNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A note deleted in Kite or outside it, kept with its history for a while so it can be restored.
 * `created_at` is when it was deleted.
 *
 * @property-read int $id
 * @property-read int $vault_id
 * @property-read string $path
 * @property-read string $title
 * @property-read string $hash
 * @property-read int $size
 * @property-read bool $locked
 * @property-read CarbonInterface|null $starred_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Vault $vault
 */
final class DeletedNote extends Model
{
    /** @use HasFactory<DeletedNoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'vault_id' => 'integer',
            'path' => 'string',
            'title' => 'string',
            'hash' => 'string',
            'size' => 'integer',
            'locked' => 'boolean',
            'starred_at' => 'datetime',
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
     * @return HasMany<NoteVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(NoteVersion::class);
    }

    /**
     * The text it had when it was deleted.
     */
    public function latestVersion(): ?NoteVersion
    {
        return $this->versions()->latest('id')->first();
    }
}
