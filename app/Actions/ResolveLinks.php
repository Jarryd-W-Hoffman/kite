<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\NoteLink;
use App\Models\Vault;
use App\Services\LinkResolver;
use App\Services\Row;
use Illuminate\Database\Eloquent\Builder;

final readonly class ResolveLinks
{
    private const int MAX_KEY_FILTER = 500;

    public function __construct(private LinkResolver $resolver)
    {
        //
    }

    /**
     * Re-resolve links against the vault's notes, returning how many links changed target.
     *
     * Without keys every link is checked; with keys only dangling links and links using those keys are.
     *
     * @param  list<string>|null  $keys
     */
    public function handle(Vault $vault, ?array $keys = null): int
    {
        $index = $this->resolver->index($vault);

        $links = NoteLink::query()
            ->where('vault_id', $vault->id)
            ->when(
                $keys !== null && count($keys) <= self::MAX_KEY_FILTER,
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $query): Builder => $query->whereNull('target_note_id')->orWhereIn('target_key', $keys ?? []),
                ),
            )
            ->toBase()
            ->select(['id', 'target_key', 'target_note_id'])
            ->lazyById(2000);

        /** @var array<int, list<int>> $changes */
        $changes = [];

        foreach ($links as $link) {
            $desired = $index[Row::string($link, 'target_key')] ?? null;

            if ($desired !== Row::nullableInt($link, 'target_note_id')) {
                $changes[$desired ?? 0][] = Row::int($link, 'id');
            }
        }

        $changed = 0;

        foreach ($changes as $targetNoteId => $linkIds) {
            foreach (array_chunk($linkIds, 500) as $chunk) {
                $changed += NoteLink::query()
                    ->whereKey($chunk)
                    ->toBase()
                    ->update(['target_note_id' => $targetNoteId === 0 ? null : $targetNoteId]);
            }
        }

        return $changed;
    }
}
