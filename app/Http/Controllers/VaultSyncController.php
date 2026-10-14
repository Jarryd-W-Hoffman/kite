<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;

final readonly class VaultSyncController
{
    public function store(Vault $vault, IndexVault $indexVault, IndexAttachments $indexAttachments): JsonResponse
    {
        $summary = $indexVault->handle($vault);
        $attachments = $indexAttachments->handle($vault);

        return response()->json([
            ...$summary,
            'attachments' => $attachments,
            'changed' => array_sum($summary) + $attachments > 0,
        ]);
    }
}
