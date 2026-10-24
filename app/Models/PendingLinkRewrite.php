<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PendingLinkRewriteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note renamed while its vault was locked. Links in locked notes aren't indexed, so they can't
 * be rewritten until the vault's key is at hand: the links whose keys pointed at the note are
 * pointed at its new path when the vault next unlocks.
 *
 * @property-read int $id
 * @property-read int $vault_id
 * @property-read list<string> $keys
 * @property-read string $path
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Vault $vault
 */
final class PendingLinkRewrite extends Model
{
    /** @use HasFactory<PendingLinkRewriteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'vault_id' => 'integer',
            'keys' => 'array',
            'path' => 'string',
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
}
