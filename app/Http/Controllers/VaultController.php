<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LoadWorkspace;
use App\Actions\OpenVault;
use App\Http\Requests\StoreVaultRequest;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final readonly class VaultController
{
    public function index(): Response
    {
        return Inertia::render('vault/index', [
            'vaults' => Vault::query()
                ->withCount('notes')
                ->latest('opened_at')
                ->get()
                ->map(fn (Vault $vault): array => [
                    ...$vault->only(['id', 'name', 'path']),
                    'notes_count' => $vault->notes_count,
                    'opened_at' => $vault->opened_at?->toIso8601String(),
                ])
                ->all(),
            'canPickFolder' => (bool) config('nativephp-internal.running'),
        ]);
    }

    public function store(StoreVaultRequest $request, OpenVault $action): RedirectResponse
    {
        try {
            $vault = $action->handle($request->string('path')->toString(), inBackground: true);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'path' => __('That folder does not exist.'),
            ]);
        }

        return to_route('vaults.show', $vault);
    }

    public function show(Vault $vault, LoadWorkspace $action): Response|RedirectResponse
    {
        $vault->update(['opened_at' => now()]);

        $landing = Note::query()
            ->where('vault_id', $vault->id)
            ->orderByRaw("CASE WHEN slug IN ('home', 'index', 'readme', 'welcome') AND path NOT LIKE '%/%' THEN 0 ELSE 1 END")
            ->latest('modified_at')
            ->first();

        if ($landing instanceof Note) {
            return to_route('notes.show', [$vault, $landing]);
        }

        return Inertia::render('note/show', $action->handle($vault));
    }

    public function destroy(Vault $vault): RedirectResponse
    {
        $vault->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Vault removed from Kite. Your files were not touched.'),
        ]);

        return to_route('vaults.index');
    }
}
