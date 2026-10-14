<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\VersionSource;
use App\Models\Note;
use App\Models\NoteVersion;
use Illuminate\Support\Facades\DB;

final readonly class RecordNoteVersion
{
    /** Saves this soon after a version was started are folded into it, so autosave doesn't flood history. */
    public const int FOLD_MINUTES = 5;

    /** Versions kept per note; older ones are dropped. */
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

    private function prune(Note $note): void
    {
        $oldestKept = $note->versions()->latest('id')->skip(self::KEEP - 1)->value('id');

        if ($oldestKept !== null) {
            $note->versions()->where('id', '<', $oldestKept)->delete();
        }
    }
}
