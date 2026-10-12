<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SearchNotes;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class VaultSearchController
{
    public function index(Request $request, Vault $vault, SearchNotes $action): JsonResponse
    {
        return response()->json([
            'results' => $action->handle($vault, mb_substr($request->string('q')->toString(), 0, 200)),
        ]);
    }
}
