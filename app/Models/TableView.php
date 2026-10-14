<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TableViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved table over the vault: the notes a query matches, with chosen frontmatter columns.
 *
 * @property-read int $id
 * @property-read int $vault_id
 * @property-read string $name
 * @property-read string $query
 * @property-read list<string> $columns
 * @property-read string|null $sort_key
 * @property-read bool $sort_desc
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Vault $vault
 */
final class TableView extends Model
{
    /** @use HasFactory<TableViewFactory> */
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
            'columns' => 'array',
            'sort_key' => 'string',
            'sort_desc' => 'boolean',
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
     * @return array{id: int, name: string, query: string, columns: list<string>, sort_key: string|null, sort_desc: bool}
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'query' => $this->query,
            'columns' => $this->columns,
            'sort_key' => $this->sort_key,
            'sort_desc' => $this->sort_desc,
        ];
    }
}
