<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vault;
use App\Services\IndexingProgress;
use Illuminate\Http\JsonResponse;

final readonly class VaultStatusController
{
    public function show(Vault $vault, IndexingProgress $progress): JsonResponse
    {
        return response()->json([
            'indexing' => $progress->get($vault),
        ]);
    }
}
