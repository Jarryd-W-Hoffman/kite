<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AppendToDailyNote;
use App\Http\Requests\StoreCaptureRequest;
use App\Listeners\OpenQuickCapture;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Native\Desktop\Facades\Window;

final readonly class CaptureController
{
    public function create(): Response
    {
        return Inertia::render('capture/create', [
            'vault' => $this->vault()?->only(['id', 'name']),
        ]);
    }

    /**
     * Append the captured text to today's note in the most recently opened vault.
     */
    public function store(StoreCaptureRequest $request, AppendToDailyNote $action): RedirectResponse
    {
        $vault = $this->vault();

        if (! $vault instanceof Vault) {
            throw ValidationException::withMessages(['text' => __('Open a vault in Kite first.')]);
        }

        try {
            $note = $action->handle($vault, $request->string('text')->toString());
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['text' => __('Today’s note is locked, so captures can’t be added to it.')]);
        }

        if (config('nativephp-internal.running')) {
            Window::close(OpenQuickCapture::WINDOW);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added to :note.', ['note' => $note->title])]);

        return to_route('capture.create');
    }

    private function vault(): ?Vault
    {
        return Vault::query()->latest('opened_at')->first();
    }
}
