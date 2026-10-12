<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Native\Desktop\Dialog;

final readonly class VaultFolderController
{
    /**
     * Show the native folder picker when running as a desktop app.
     */
    public function store(): JsonResponse
    {
        if (! config('nativephp-internal.running')) {
            return response()->json(['path' => null]);
        }

        $dialog = resolve(Dialog::class)
            ->title(__('Open a folder as a vault'))
            ->button(__('Open vault'));

        $dialog->folders();

        $path = $dialog->open();

        return response()->json(['path' => is_string($path) ? $path : null]);
    }
}
