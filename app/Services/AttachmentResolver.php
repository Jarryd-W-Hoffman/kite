<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vault;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class AttachmentResolver
{
    /**
     * Resolve embed targets such as "photo.png" or "images/photo.png" to attachment paths.
     *
     * Matches next to the note win, then the match closest to the vault root.
     *
     * @param  list<string>  $targets
     * @return array<string, string>
     */
    public function resolve(Vault $vault, array $targets, string $noteFolder = ''): array
    {
        $keys = [];

        foreach ($targets as $target) {
            $key = mb_strtolower(mb_ltrim(str_replace('\\', '/', mb_trim($target)), './'));

            if ($key !== '') {
                $keys[$target] = $key;
            }
        }

        if ($keys === []) {
            return [];
        }

        $names = array_values(array_unique(array_filter($keys, fn (string $key): bool => ! str_contains($key, '/'))));
        $paths = array_values(array_unique(array_filter($keys, fn (string $key): bool => str_contains($key, '/'))));

        $candidates = DB::table('attachments')
            ->where('vault_id', $vault->id)
            ->where(function (Builder $query) use ($names, $paths): void {
                $query->whereIn('name_key', $names)->orWhereIn('path_key', $paths);

                foreach ($paths as $path) {
                    $query->orWhere('path_key', 'like', '%/'.addcslashes($path, '%_\\'), '\\');
                }
            })
            ->get(['path', 'path_key', 'name_key'])
            ->map(fn (object $row): array => [
                'path' => Row::string($row, 'path'),
                'path_key' => Row::string($row, 'path_key'),
                'name_key' => Row::string($row, 'name_key'),
            ])
            ->all();

        $folderKey = mb_strtolower(mb_trim($noteFolder, '/'));

        usort($candidates, fn (array $a, array $b): int => [
            $this->folderOf($a['path_key']) === $folderKey ? 0 : 1,
            mb_substr_count($a['path_key'], '/'),
            $a['path_key'],
        ] <=> [
            $this->folderOf($b['path_key']) === $folderKey ? 0 : 1,
            mb_substr_count($b['path_key'], '/'),
            $b['path_key'],
        ]);

        $resolved = [];

        foreach ($keys as $target => $key) {
            foreach ($candidates as $candidate) {
                $matches = str_contains($key, '/')
                    ? $candidate['path_key'] === $key || str_ends_with($candidate['path_key'], '/'.$key)
                    : $candidate['name_key'] === $key;

                if ($matches) {
                    $resolved[$target] = $candidate['path'];

                    break;
                }
            }
        }

        return $resolved;
    }

    private function folderOf(string $pathKey): string
    {
        return str_contains($pathKey, '/') ? dirname($pathKey) : '';
    }
}
