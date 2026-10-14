<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GroupColour;
use Carbon\CarbonInterface;
use Database\Factories\ColourGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $vault_id
 * @property-read string $name
 * @property-read string $query
 * @property-read GroupColour $colour
 * @property-read int $position
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Vault $vault
 */
final class ColourGroup extends Model
{
    /** @use HasFactory<ColourGroupFactory> */
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
            'colour' => GroupColour::class,
            'position' => 'integer',
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
     * @return array{id: int, name: string, query: string, colour: string}
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'query' => $this->query,
            'colour' => $this->colour->value,
        ];
    }
}
