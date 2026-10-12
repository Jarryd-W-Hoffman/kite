<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Note;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Builder;

final readonly class LinkResolver
{
    /**
     * Every link key that could point at a note with the given path key, e.g. "a/b/c", "b/c" and "c".
     *
     * @return list<string>
     */
    public function keysFor(string $pathKey): array
    {
        $segments = explode('/', $pathKey);

        return array_map(
            fn (int $offset): string => implode('/', array_slice($segments, $offset)),
            array_keys($segments),
        );
    }

    /**
     * Resolve link target keys to note ids, preferring the match closest to the vault root.
     *
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    public function resolve(Vault $vault, array $keys): array
    {
        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            return [];
        }

        $nameKeys = array_values(array_filter($keys, fn (string $key): bool => ! str_contains($key, '/')));
        $pathKeys = array_values(array_filter($keys, fn (string $key): bool => str_contains($key, '/')));

        $candidates = Note::query()
            ->where('vault_id', $vault->id)
            ->where(function (Builder $query) use ($nameKeys, $pathKeys): void {
                $query->whereIn('slug', $nameKeys)->orWhereIn('path_key', $pathKeys);

                foreach ($pathKeys as $pathKey) {
                    $query->orWhere('path_key', 'like', '%/'.addcslashes($pathKey, '%_\\'), '\\');
                }
            })
            ->get(['id', 'slug', 'path_key'])
            ->sortBy([
                fn (Note $a, Note $b): int => mb_substr_count($a->path_key, '/') <=> mb_substr_count($b->path_key, '/'),
                fn (Note $a, Note $b): int => strcmp($a->path_key, $b->path_key),
            ]);

        $resolved = [];

        foreach ($keys as $key) {
            $match = $candidates->first(fn (Note $note): bool => str_contains($key, '/')
                ? $note->path_key === $key || str_ends_with($note->path_key, '/'.$key)
                : $note->slug === $key);

            if ($match instanceof Note) {
                $resolved[$key] = $match->id;
            }
        }

        return $resolved;
    }
}
