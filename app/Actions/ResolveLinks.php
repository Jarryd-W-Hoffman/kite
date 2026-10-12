<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\NoteLink;
use App\Models\Vault;
use App\Services\LinkResolver;
use Illuminate\Database\Eloquent\Builder;

final readonly class ResolveLinks
{
    public function __construct(private LinkResolver $resolver)
    {
        //
    }

    /**
     * Re-resolve dangling links, plus any links using the given keys, returning how many links changed.
     *
     * @param  list<string>  $keys
     */
    public function handle(Vault $vault, array $keys = []): int
    {
        /** @var list<string> $danglingKeys */
        $danglingKeys = NoteLink::query()
            ->where('vault_id', $vault->id)
            ->whereNull('target_note_id')
            ->distinct()
            ->pluck('target_key')
            ->all();

        $changed = 0;

        foreach (array_chunk(array_values(array_unique([...$danglingKeys, ...$keys])), 500) as $chunk) {
            $resolved = $this->resolver->resolve($vault, $chunk);

            foreach ($chunk as $key) {
                $noteId = $resolved[$key] ?? null;

                $changed += NoteLink::query()
                    ->where('vault_id', $vault->id)
                    ->where('target_key', $key)
                    ->where(fn (Builder $query): Builder => $noteId === null
                        ? $query->whereNotNull('target_note_id')
                        : $query->whereNull('target_note_id')->orWhere('target_note_id', '!=', $noteId))
                    ->update(['target_note_id' => $noteId]);
            }
        }

        return $changed;
    }
}
