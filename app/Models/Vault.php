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
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Vault extends Model
{
    /** @use HasFactory<VaultFactory> */
    use HasFactory;

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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
