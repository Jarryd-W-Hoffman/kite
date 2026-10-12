<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\IndexVault;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;

final readonly class VaultSyncController
{
    public function store(Vault $vault, IndexVault $action): JsonResponse
    {
        $summary = $action->handle($vault);

        return response()->json([
            ...$summary,
            'changed' => array_sum($summary) > 0,
        ]);
    }
}
