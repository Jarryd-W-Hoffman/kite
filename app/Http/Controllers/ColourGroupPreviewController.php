<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vault;
use App\Services\ColourGroupQuery;
use App\Services\ColourGroups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final readonly class ColourGroupPreviewController
{
    /**
     * How many notes a query would match, so the group editor can show a live count.
     */
    public function show(Request $request, Vault $vault, ColourGroups $colourGroups): JsonResponse
    {
        try {
            $query = ColourGroupQuery::parse(mb_substr($request->string('query')->toString(), 0, 500));
        } catch (InvalidArgumentException $invalidArgumentException) {
            return response()->json(['count' => null, 'error' => $invalidArgumentException->getMessage()]);
        }

        return response()->json(['count' => $colourGroups->count($vault, $query), 'error' => null]);
    }
}
