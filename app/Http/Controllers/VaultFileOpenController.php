<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaultFileOpenRequest;
use App\Models\Vault;
use App\Services\AttachmentTypes;
use App\Services\VaultFilesystem;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Native\Desktop\Facades\Shell;

final readonly class VaultFileOpenController
{
    /**
     * Open an attachment in the app the system uses for it (desktop app only), such as an image
     * viewer or a PDF reader.
     */
    public function store(StoreVaultFileOpenRequest $request, Vault $vault, VaultFilesystem $files, AttachmentTypes $types): Response
    {
        abort_unless((bool) config('nativephp-internal.running'), 404);

        try {
            $path = $files->normalise($request->string('path')->toString());
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_unless($types->isAttachment($path) && $files->exists($vault, $path), 404);

        Shell::openFile($files->absolutePath($vault, $path));

        return response()->noContent();
    }
}
