<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vault;
use App\Services\AttachmentTypes;
use App\Services\VaultFilesystem;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class VaultFileController
{
    /**
     * Serve an attachment from inside the vault. Files are sandboxed so an SVG cannot run script in the app.
     */
    public function show(Vault $vault, string $path, VaultFilesystem $files, AttachmentTypes $types): BinaryFileResponse
    {
        try {
            $path = $files->normalise($path);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_unless($types->isAttachment($path) && $files->exists($vault, $path), 404);

        $response = response()->file($files->absolutePath($vault, $path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);

        $response->setPrivate();
        $response->setAutoEtag();
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }
}
