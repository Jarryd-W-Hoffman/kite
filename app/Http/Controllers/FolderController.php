<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateFolder;
use App\Http\Requests\StoreFolderRequest;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class FolderController
{
    public function store(StoreFolderRequest $request, Vault $vault, CreateFolder $action): RedirectResponse
    {
        try {
            $action->handle(
                $vault,
                $request->string('name')->toString(),
                $request->filled('parent') ? $request->string('parent')->toString() : null,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            $message = $invalidArgumentException->getMessage();

            throw ValidationException::withMessages([
                'name' => match (true) {
                    str_contains($message, 'already exists') => __('A folder or file with that name already exists.'),
                    str_contains($message, 'is ignored') => __('That folder is ignored in Preferences, so it would not be shown.'),
                    default => __('That folder name is not valid.'),
                },
            ]);
        }

        return back();
    }
}
