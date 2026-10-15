<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vault;
use InvalidArgumentException;
use JsonException;

/**
 * Canvases: `.canvas` files in the vault, in the open JSON Canvas format (jsoncanvas.org), so
 * they can be opened in other apps such as Obsidian. A canvas holds cards (`nodes`) and the
 * arrows between them (`edges`). Kite only reads and writes the files; it keeps no index of them.
 */
final readonly class Canvases
{
    public const string EXTENSION = 'canvas';

    public function __construct(private VaultFilesystem $files)
    {
        //
    }

    /**
     * @return list<array{path: string, name: string}>
     */
    public function all(Vault $vault): array
    {
        return array_map(fn (string $path): array => [
            'path' => $path,
            'name' => $this->name($path),
        ], array_keys($this->files->filesWithExtensions($vault, [self::EXTENSION])));
    }

    public function isCanvas(string $path): bool
    {
        return mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) === self::EXTENSION;
    }

    public function name(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * A canvas's cards and connections, anything else at the top of the file, and the hash of
     * the file they came from. An empty file is an empty canvas, as other apps create them.
     *
     * @return array{nodes: list<array<array-key, mixed>>, edges: list<array<array-key, mixed>>, extra: array<array-key, mixed>, hash: string}
     *
     * @throws InvalidArgumentException when the file isn't a canvas
     */
    public function read(Vault $vault, string $path): array
    {
        $contents = $this->files->read($vault, $path);

        try {
            $canvas = mb_trim($contents) === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException("The canvas [{$path}] isn't valid JSON.");
        }

        throw_unless(is_array($canvas) && ($canvas === [] || ! array_is_list($canvas)), InvalidArgumentException::class, "The canvas [{$path}] isn't a JSON Canvas file.");

        $nodes = $this->items($canvas['nodes'] ?? null);
        $edges = $this->items($canvas['edges'] ?? null);

        unset($canvas['nodes'], $canvas['edges']);

        return ['nodes' => $nodes, 'edges' => $edges, 'extra' => $canvas, 'hash' => $this->hash($contents)];
    }

    /**
     * Write a canvas the way Obsidian does (tab-indented JSON), returning the new file's hash.
     *
     * @param  list<array<array-key, mixed>>  $nodes
     * @param  list<array<array-key, mixed>>  $edges
     * @param  array<array-key, mixed>  $extra  Anything else to keep at the top of the file.
     */
    public function write(Vault $vault, string $path, array $nodes, array $edges, array $extra = []): string
    {
        $canvas = [...$extra, 'nodes' => $nodes, 'edges' => $edges];

        $json = (string) json_encode($canvas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $json = (string) preg_replace_callback('/^(?: {4})+/m', fn (array $indent): string => str_repeat("\t", intdiv(mb_strlen($indent[0]), 4)), $json);

        $this->files->write($vault, $path, $json);

        return $this->hash($json);
    }

    public function hash(string $contents): string
    {
        return hash('xxh128', $contents);
    }

    /**
     * Create an empty canvas, numbering the name when it's taken, and return its path.
     */
    public function create(Vault $vault, string $name, ?string $folder = null): string
    {
        $name = mb_trim(str_replace(['/', '\\'], ' ', $name)) ?: 'Untitled';
        $base = ($folder === null || mb_trim($folder) === '' ? '' : $this->files->normalise($folder).'/').$name;
        $path = $this->files->normalise($base.'.'.self::EXTENSION);

        for ($number = 2; $this->files->exists($vault, $path); $number++) {
            $path = $this->files->normalise("{$base} {$number}.".self::EXTENSION);
        }

        $this->write($vault, $path, [], []);

        return $path;
    }

    /**
     * Point cards that show a moved file at its new path, in every canvas. Returns how many
     * canvases changed.
     */
    public function moveFile(Vault $vault, string $from, string $to): int
    {
        $changed = 0;

        foreach ($this->all($vault) as ['path' => $path]) {
            try {
                ['nodes' => $nodes, 'edges' => $edges, 'extra' => $extra] = $this->read($vault, $path);
            } catch (InvalidArgumentException) {
                continue;
            }

            $moved = false;

            foreach ($nodes as $index => $node) {
                if (($node['type'] ?? null) === 'file' && ($node['file'] ?? null) === $from) {
                    $nodes[$index]['file'] = $to;
                    $moved = true;
                }
            }

            if ($moved) {
                $this->write($vault, $path, $nodes, $edges, $extra);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function items(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, is_array(...))) : [];
    }
}
