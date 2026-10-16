<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LoadFlashcards;
use App\Actions\LoadWorkspace;
use App\Models\Vault;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class FlashcardController
{
    /**
     * The decks, and today's cards to study. "Today" is the reviewer's local date, sent by the
     * window, so cards fall due at their midnight rather than the server's.
     */
    public function index(Request $request, Vault $vault, LoadWorkspace $workspace, LoadFlashcards $flashcards): Response
    {
        $request->validate([
            'deck' => ['nullable', 'string', 'max:200'],
            'today' => ['nullable', 'date_format:Y-m-d'],
            'study' => ['nullable', 'boolean'],
        ]);

        $today = $request->filled('today') ? $request->string('today')->toString() : now()->toDateString();
        $deck = $request->filled('deck') ? $request->string('deck')->toString() : null;

        return Inertia::render('flashcards/index', [
            ...$workspace->handle($vault),
            ...$flashcards->handle($vault, $today, $deck),
            'deck' => $deck,
            'today' => $today,
            'studying' => $request->boolean('study'),
        ]);
    }
}
