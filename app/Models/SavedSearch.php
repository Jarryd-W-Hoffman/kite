<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A search kept in the sidebar: text and filters (`tag:`, `folder:`, `-tag:`, `status:done`)
 * whose results stay up to date with the vault.
 *
 * @property-read int $id
 * @property-read int $vault_id
 * @property-read string $name
 * @property-read string $query
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Vault $vault
 */
final class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'vault_id' => 'integer',
            'name' => 'string',
            'query' => 'string',
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
     * @return array{id: int, name: string, query: string}
     */
    public function toSummary(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'query' => $this->query];
    }
}
