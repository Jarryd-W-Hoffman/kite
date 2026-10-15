<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\VaultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $path
 * @property-read CarbonInterface|null $opened_at
 * @property-read CarbonInterface|null $indexed_at
 * @property-read int $tree_revision
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Vault extends Model
{
    /** @use HasFactory<VaultFactory> */
    use HasFactory;

    /**
     * Record that something the sidebar shows changed: which notes there are, their paths,
     * titles, tags, properties or lock, the folders, or the colour groups. The workspace's file
     * tree and friends are sent once per revision, so the window only downloads them again after
     * this is called.
     */
    public static function markTreeChanged(int $vaultId): void
    {
        self::query()->whereKey($vaultId)->increment('tree_revision');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'path' => 'string',
            'opened_at' => 'datetime',
            'indexed_at' => 'datetime',
            'tree_revision' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The tree revision as stored now, which may be newer than this model's copy.
     */
    public function currentTreeRevision(): int
    {
        return self::query()->whereKey($this->id)->sole(['tree_revision'])->tree_revision;
    }

    /**
     * @return HasMany<SavedSearch, $this>
     */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * @return HasMany<TableView, $this>
     */
    public function tableViews(): HasMany
    {
        return $this->hasMany(TableView::class);
    }

    /**
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * @return HasMany<NoteLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(NoteLink::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Colour groups in the order they are matched: a note takes the first group it matches.
     *
     * @return HasMany<ColourGroup, $this>
     */
    public function colourGroups(): HasMany
    {
        return $this->hasMany(ColourGroup::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<NoteTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(NoteTag::class);
    }
}
