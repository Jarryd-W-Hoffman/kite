<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RenderMarkdown;
use App\Http\Requests\PreviewNoteRequest;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;

final readonly class VaultPreviewController
{
    public function store(PreviewNoteRequest $request, Vault $vault, RenderMarkdown $action): JsonResponse
    {
        return response()->json([
            'html' => $action->handle(
                $vault,
                $request->string('contents')->toString(),
                $request->filled('path') ? $request->string('path')->toString() : null,
            ),
        ]);
    }
}
