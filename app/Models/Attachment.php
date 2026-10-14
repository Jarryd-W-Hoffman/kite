<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $vault_id
 * @property-read string $path
 * @property-read string $path_key
 * @property-read string $name_key
 * @property-read string $extension
 * @property-read int $size
 * @property-read int $modified_at
 * @property-read Vault $vault
 */
#[WithoutTimestamps]
final class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
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
            'path_key' => 'string',
            'name_key' => 'string',
            'extension' => 'string',
            'size' => 'integer',
            'modified_at' => 'integer',
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
