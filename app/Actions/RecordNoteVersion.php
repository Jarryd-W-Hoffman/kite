<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\VersionSource;
use App\Models\Note;
use App\Models\NoteVersion;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class RecordNoteVersion
{
    /** Saves this soon after a version was started are folded into it, so autosave doesn't flood history. */
    public const int FOLD_MINUTES = 5;

    /** At most this many versions are kept per note; older ones are dropped. */
    public const int KEEP = 100;

    /**
     * Record that a note's text went from `$previous` (what was on disk, null for a new file) to
     * `$contents`. Text on disk that history has never seen (the original, or a change made in
     * another app) is kept as its own version first, so it can always be restored.
     */
    public function handle(Note $note, ?string $previous, string $contents, VersionSource $source = VersionSource::Edit): void
    {
        DB::transaction(function () use ($note, $previous, $contents, $source): void {
            $latest = $note->versions()->latest('id')->first();
            $keptPrevious = false;

            if ($previous !== null && $latest?->hash !== hash('sha1', $previous)) {
                $latest = $this->create($note, $previous, VersionSource::External);
                $keptPrevious = true;
            }

            if ($latest?->hash === hash('sha1', $contents)) {
                return;
            }

            if (! $keptPrevious && $this->canFold($latest, $source)) {
                $latest->update(['contents' => $contents, 'hash' => hash('sha1', $contents)]);
            } else {
                $this->create($note, $contents, $source);
            }

            $this->prune($note);
        });
    }

    /**
     * @phpstan-assert-if-true NoteVersion $latest
     */
    private function canFold(?NoteVersion $latest, VersionSource $source): bool
    {
        return $source === VersionSource::Edit
            && $latest?->source === VersionSource::Edit
            && $latest->created_at->greaterThan(now()->subMinutes(self::FOLD_MINUTES));
    }

    private function create(Note $note, string $contents, VersionSource $source): NoteVersion
    {
        return $note->versions()->create([
            'contents' => $contents,
            'hash' => hash('sha1', $contents),
            'source' => $source,
        ]);
    }

    /**
     * Thin out older history: every version from the last day, then the last one of each hour
     * for a week, of each day for 90 days and of each week after that, up to KEEP in all.
     */
    private function prune(Note $note): void
    {
        $now = now();
        $kept = [];
        $dropped = [];

        foreach ($note->versions()->latest('id')->get(['id', 'updated_at']) as $version) {
            $period = $this->period($version->updated_at, $now);

            if (count($kept) >= self::KEEP || ($period !== null && isset($kept[$period]))) {
                $dropped[] = $version->id;
            } else {
                $kept[$period ?? 'version '.$version->id] = true;
            }
        }

        if ($dropped !== []) {
            NoteVersion::query()->whereKey($dropped)->delete();
            DB::statement('PRAGMA incremental_vacuum');
        }
    }

    /**
     * The stretch of time a version shares with others, keeping only the newest; null for
     * versions recent enough to all be kept.
     */
    private function period(CarbonInterface $savedAt, CarbonInterface $now): ?string
    {
        return match (true) {
            $savedAt->greaterThan($now->subDay()) => null,
            $savedAt->greaterThan($now->subWeek()) => 'hour '.$savedAt->format('Y-m-d H'),
            $savedAt->greaterThan($now->subDays(90)) => 'day '.$savedAt->format('Y-m-d'),
            default => 'week '.$savedAt->format('o-W'),
        };
    }
}
