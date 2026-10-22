<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\VersionSource;
use App\Models\DeletedNote;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveNote
{
    public function __construct(private RecordNoteVersion $recordVersion)
    {
        //
    }

    /**
     * Keep a note's history before it is deleted, so it can be restored for a while. A note
     * deleted in Kite passes its file's text, which becomes its last version, so even a note
     * never edited in Kite can come back. One deleted outside Kite has only the history Kite
     * kept; without any, there is nothing to restore and nothing is kept.
     */
    public function handle(Note $note, ?string $contents = null): ?DeletedNote
    {
        return DB::transaction(function () use ($note, $contents): ?DeletedNote {
            $this->purge($note->vault_id);

            if ($contents !== null) {
                $this->recordVersion->handle($note, $contents, $contents, VersionSource::External);
            }

            if (! $note->versions()->exists()) {
                return null;
            }

            $deleted = DeletedNote::query()->create([
                'vault_id' => $note->vault_id,
                'path' => $note->path,
                'title' => $note->title,
                'hash' => $note->hash,
                'size' => $note->size,
                'locked' => $note->locked,
                'starred_at' => $note->starred_at,
            ]);

            $note->versions()->toBase()->update(['note_id' => null, 'deleted_note_id' => $deleted->id]);

            return $deleted;
        });
    }

    /**
     * Forget deleted notes kept longer than the configured number of days.
     */
    public function purge(int $vaultId): void
    {
        DeletedNote::query()
            ->where('vault_id', $vaultId)
            ->where('created_at', '<', now()->subDays(max(1, config()->integer('kite.deleted_notes.keep_days'))))
            ->delete();
    }

    /**
     * Keep the history of notes deleted outside Kite, found missing while indexing.
     *
     * @param  list<int>  $noteIds
     */
    public function archiveMissing(Vault $vault, array $noteIds): void
    {
        foreach (array_chunk($noteIds, 500) as $ids) {
            Note::query()
                ->where('vault_id', $vault->id)
                ->whereKey($ids)
                ->whereHas('versions')
                ->get()
                ->each(fn (Note $note): ?DeletedNote => $this->handle($note));
        }
    }
}
