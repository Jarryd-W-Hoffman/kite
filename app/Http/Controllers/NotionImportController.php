<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ImportNotionExport;
use App\Http\Requests\StoreNotionImportRequest;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final readonly class NotionImportController
{
    public function store(StoreNotionImportRequest $request, Vault $vault, ImportNotionExport $action): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $imported = $action->handle($vault, (string) $file->getRealPath(), $request->string('folder', 'Notion')->toString());
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'file' => __('That file is not a Notion export. Export as “Markdown & CSV” and choose the zip.'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{1} Imported :count note into :folder.|[2,*] Imported :count notes into :folder.',
            $imported['notes'],
            ['folder' => $imported['folder']],
        )]);

        return to_route('vaults.show', $vault);
    }
}
