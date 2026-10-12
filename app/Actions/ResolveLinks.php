<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\NoteLink;
use App\Models\Vault;
use App\Services\LinkResolver;

final readonly class ResolveLinks
{
    public function __construct(private LinkResolver $resolver)
    {
        //
    }

    /**
     * Point dangling links at notes that now exist, returning how many links were resolved.
     */
    public function handle(Vault $vault): int
    {
        /** @var list<string> $keys */
        $keys = NoteLink::query()
            ->where('vault_id', $vault->id)
            ->whereNull('target_note_id')
            ->distinct()
            ->pluck('target_key')
            ->all();

        $resolved = 0;

        foreach (array_chunk($keys, 500) as $chunk) {
            foreach ($this->resolver->resolve($vault, $chunk) as $key => $noteId) {
                $resolved += NoteLink::query()
                    ->where('vault_id', $vault->id)
                    ->whereNull('target_note_id')
                    ->where('target_key', $key)
                    ->update(['target_note_id' => $noteId]);
            }
        }

        return $resolved;
    }
}
