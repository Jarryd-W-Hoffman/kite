<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vault;
use App\Services\Flashcards\Scheduler;
use App\Services\Flashcards\ScheduleStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class FlashcardReviewController
{
    /**
     * Record how well a card was remembered, returning when it's next due and, for a card shown
     * again today, the intervals its buttons now lead to.
     */
    public function store(Request $request, Vault $vault, ScheduleStore $schedules, Scheduler $scheduler): JsonResponse
    {
        $request->validate([
            'key' => ['required', 'string', 'regex:/^[0-9a-f]{20}$/'],
            'grade' => ['required', Rule::in(Scheduler::GRADES)],
            'today' => ['required', 'date_format:Y-m-d'],
        ]);

        $key = $request->string('key')->toString();
        $today = $request->string('today')->toString();
        $state = $scheduler->review($schedules->all($vault)[$key] ?? null, $request->string('grade')->toString(), $today);

        $schedules->put($vault, $key, $state);

        return response()->json([
            'due' => $state['due'],
            'interval' => $state['interval'],
            'intervals' => $scheduler->preview($state, $today),
        ]);
    }
}
