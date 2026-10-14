<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StoreAttachment;
use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

final readonly class VaultAttachmentController
{
    public function store(StoreAttachmentRequest $request, Vault $vault, StoreAttachment $action): JsonResponse
    {
        $file = $request->file('file');
        assert($file instanceof UploadedFile);

        return response()->json($action->handle($vault, $file), 201);
    }
}
