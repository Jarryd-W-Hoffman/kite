<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreExternalLinkRequest;
use Illuminate\Http\Response;
use Native\Desktop\Facades\Shell;

final readonly class ExternalLinkController
{
    /**
     * Open a web link in the system browser (desktop app only), not in a bare app window.
     */
    public function store(StoreExternalLinkRequest $request): Response
    {
        abort_unless((bool) config('nativephp-internal.running'), 404);

        Shell::openExternal($request->string('url')->toString());

        return response()->noContent();
    }
}
