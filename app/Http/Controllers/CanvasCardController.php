<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DescribeCanvasFiles;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class CanvasCardController
{
    /**
     * What files added to a canvas show, for cards added in the window.
     */
    public function index(Request $request, Vault $vault, DescribeCanvasFiles $describe): JsonResponse
    {
        $request->validate([
            'paths' => ['required', 'array', 'max:100'],
            'paths.*' => ['string', 'max:1024'],
        ]);

        $paths = array_values(array_filter($request->array('paths'), is_string(...)));

        return response()->json(['files' => (object) $describe->handle($vault, $paths)]);
    }
}
