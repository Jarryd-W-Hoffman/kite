<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Models\Vault;
use App\Services\IndexingProgress;
use Illuminate\Http\JsonResponse;

final readonly class VaultSyncController
{
    public function store(Vault $vault, IndexVault $indexVault, IndexAttachments $indexAttachments, IndexingProgress $progress): JsonResponse
    {
        $indexing = $progress->get($vault);

        if ($indexing !== null) {
            return response()->json([
                'added' => 0,
                'updated' => 0,
                'removed' => 0,
                'moved' => 0,
                'attachments' => 0,
                'changed' => false,
                'indexing' => $indexing,
            ]);
        }

        $summary = $indexVault->handle($vault);
        $attachments = $indexAttachments->handle($vault);

        return response()->json([
            ...$summary,
            'attachments' => $attachments,
            'changed' => array_sum($summary) + $attachments > 0,
            'indexing' => null,
        ]);
    }
}
