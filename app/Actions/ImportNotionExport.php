<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\VaultFilesystem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use ZipArchive;

final readonly class ImportNotionExport
{
    /** Notion appends a 32-character id to every page, folder and database name. */
    private const string NOTION_ID = '/\s+[0-9a-f]{32}(?=$|_all$)/i';

    private const string LINK = '/(!?)\[([^\]\n]*)\]\(([^)\s]+)\)/u';

    private const array LIST_PROPERTIES = ['tags', 'tag', 'labels', 'categories', 'category'];

    public function __construct(
        private VaultFilesystem $files,
        private IndexVault $indexVault,
        private IndexAttachments $indexAttachments,
    ) {
        //
    }

    /**
     * Import a Notion "Markdown & CSV" export (a zip, possibly holding more zips) into a folder of
     * the vault. Pages become notes without Notion's ids in their names, links between pages
     * become wiki links, images become embeds, database rows get their properties as frontmatter,
     * and each database gets a table view of its rows.
     *
     * @return array{notes: int, attachments: int, views: int, folder: string}
     */
    public function handle(Vault $vault, string $zipPath, string $folder = 'Notion'): array
    {
        $temporary = [];

        try {
            $entries = $this->entries($zipPath, $temporary);

            throw_if($entries === [], InvalidArgumentException::class, 'That file is not a Notion export.');

            return $this->import($vault, $entries, $folder);
        } finally {
            array_map(unlink(...), $temporary);
        }
    }

    /**
     * @param  array<string, array{0: ZipArchive, 1: int}>  $entries
     * @return array{notes: int, attachments: int, views: int, folder: string}
     */
    private function import(Vault $vault, array $entries, string $folder): array
    {
        $folder = $this->freeFolder($vault, $this->files->normalise($this->clean($folder) ?: 'Notion'));
        $paths = $this->plan(array_keys($entries), $folder);
        $databases = $this->databases($entries, $paths);
        $names = array_count_values(array_map(
            fn (string $path): string => mb_strtolower(basename($path, '.md')),
            array_filter($paths, fn (string $path): bool => str_ends_with($path, '.md')),
        ));

        $counts = ['notes' => 0, 'attachments' => 0, 'views' => 0];

        foreach ($entries as $original => $entry) {
            $path = $paths[$original];

            if (str_ends_with(mb_strtolower($original), '.csv')) {
                continue;
            }

            // Files are read one at a time, so large exports don't need to fit in memory.
            $contents = (string) $entry[0]->getFromIndex($entry[1]);

            if (str_ends_with($path, '.md')) {
                $contents = $this->convert($contents, $original, $paths, $names, $databases[dirname($original)]['columns'] ?? []);
                $counts['notes']++;
            } else {
                $counts['attachments']++;
            }

            $this->files->write($vault, $path, $contents);
        }

        DB::transaction(function () use ($vault, $databases, &$counts): void {
            foreach ($databases as $database) {
                $vault->tableViews()->create([
                    'name' => $database['name'],
                    'query' => 'folder:"'.$database['folder'].'"',
                    'columns' => array_slice($database['columns'], 0, 8),
                ]);
                $counts['views']++;
            }
        });

        $this->indexVault->handle($vault);
        $this->indexAttachments->handle($vault);

        return [...$counts, 'folder' => $folder];
    }

    /**
     * Every file in the export by its path inside the export, with the archive and index to read
     * it from, including the files in zips inside it (Notion splits large exports that way, one
     * level deep). Paths are kept relative and never leave the export.
     *
     * @param  list<string>  $temporary  Files extracted to read nested zips, to delete afterwards.
     * @return array<string, array{0: ZipArchive, 1: int}>
     */
    private function entries(string $zipPath, array &$temporary, bool $nested = false): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return [];
        }

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $path = $this->safePath($name);

            if ($path === null || str_ends_with($name, '/')) {
                continue;
            }

            if (str_ends_with(mb_strtolower($path), '.zip')) {
                if (! $nested) {
                    $inner = sys_get_temp_dir().'/kite-notion-'.bin2hex(random_bytes(8)).'.zip';
                    $temporary[] = $inner;
                    copy('zip://'.$zipPath.'#'.$name, $inner);
                    $entries += $this->entries($inner, $temporary, nested: true);
                }

                continue;
            }

            $entries[$path] = [$zip, $index];
        }

        return $entries;
    }

    private function safePath(string $name): ?string
    {
        $segments = array_filter(explode('/', str_replace('\\', '/', $name)), fn (string $segment): bool => $segment !== '' && $segment !== '.');

        if ($segments === [] || in_array('..', $segments, true) || str_starts_with(end($segments), '.') || array_first($segments) === '__MACOSX') {
            return null;
        }

        return implode('/', $segments);
    }

    /**
     * Where each file goes: inside the import folder, with Notion's ids and characters notes
     * can't have taken out of every name, and names made unique where that made two the same.
     *
     * @param  list<string>  $originals
     * @return array<string, string>
     */
    private function plan(array $originals, string $folder): array
    {
        $paths = [];
        $taken = [];

        foreach ($originals as $original) {
            $segments = explode('/', $original);
            $file = array_pop($segments);
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $name = $this->clean(pathinfo($file, PATHINFO_FILENAME)) ?: 'Untitled';
            $directory = implode('/', array_map(fn (string $segment): string => $this->clean($segment) ?: 'Untitled', $segments));
            $base = $folder.($directory === '' ? '' : '/'.$directory).'/'.$name;
            $suffix = $extension === '' ? '' : '.'.mb_strtolower($extension);
            $path = $base.$suffix;

            for ($number = 1; isset($taken[mb_strtolower($path)]); $number++) {
                $path = "{$base} {$number}{$suffix}";
            }

            $taken[mb_strtolower($path)] = true;
            $paths[$original] = $path;
        }

        return $paths;
    }

    private function clean(string $name): string
    {
        $name = (string) preg_replace(self::NOTION_ID, '', $name);
        $name = (string) preg_replace('/_all$/', '', $name);
        $name = (string) preg_replace('/[\\\\\/:*?"<>|#^\[\]\x00-\x1F]+/u', ' ', $name);

        return mb_trim((string) preg_replace('/\s+/u', ' ', $name), ' .');
    }

    private function freeFolder(Vault $vault, string $folder): string
    {
        $free = $folder;

        for ($number = 1; $this->files->folderExists($vault, $free) || $this->files->exists($vault, $free); $number++) {
            $free = "{$folder} {$number}";
        }

        return $free;
    }

    /**
     * Databases exported as CSV files, by the export folder holding their row pages.
     *
     * @param  array<string, array{0: ZipArchive, 1: int}>  $entries
     * @param  array<string, string>  $paths
     * @return array<string, array{name: string, folder: string, columns: list<string>}>
     */
    private function databases(array $entries, array $paths): array
    {
        $databases = [];

        foreach ($entries as $original => [$zip, $index]) {
            if (! str_ends_with(mb_strtolower($original), '.csv')) {
                continue;
            }

            $rows = (string) preg_replace('/^\x{FEFF}/u', '', (string) $zip->getFromIndex($index));
            $header = str_getcsv(strtok($rows, "\r\n") ?: '', escape: '');
            $rowsFolder = (string) preg_replace(['/_all\.csv$/i', '/\.csv$/i'], '', $original);
            $folder = (string) preg_replace('/\.csv$/', '', $paths[$original]);

            // "_all" copies list the same rows as the plain CSV beside them.
            if (isset($databases[$rowsFolder])) {
                continue;
            }

            $databases[$rowsFolder] = [
                'name' => basename($folder),
                'folder' => $folder,
                'columns' => array_values(array_filter(
                    array_map(fn (?string $column): string => mb_trim((string) $column), array_slice($header, 1)),
                    fn (string $column): bool => $column !== '' && preg_match('/^[^:#\n\[\]{},]{1,100}$/u', $column) === 1,
                )),
            ];
        }

        return $databases;
    }

    /**
     * @param  array<string, string>  $paths
     * @param  array<string, int>  $names
     * @param  list<string>  $columns
     */
    private function convert(string $contents, string $original, array $paths, array $names, array $columns): string
    {
        $contents = str_replace("\r\n", "\n", (string) preg_replace('/^\x{FEFF}/u', '', $contents));
        $contents = $this->callouts($contents);
        $contents = $this->links($contents, $original, $paths, $names);

        return $columns === [] ? $contents : $this->properties($contents, $columns);
    }

    /**
     * Point links at the imported files: pages as wiki links, images as embeds, other files as
     * links relative to the note.
     *
     * @param  array<string, string>  $paths
     * @param  array<string, int>  $names
     */
    private function links(string $contents, string $original, array $paths, array $names): string
    {
        $directory = dirname($original);

        return (string) preg_replace_callback(self::LINK, function (array $match) use ($directory, $original, $paths, $names): string {
            [$whole, $bang, $label, $href] = $match;

            if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $href) === 1 || str_starts_with($href, '#')) {
                return $whole;
            }

            $target = $this->resolve($directory, rawurldecode(strtok($href, '#')));

            if ($target === null || ! isset($paths[$target])) {
                return $whole;
            }

            $path = $paths[$target];

            if (str_ends_with(mb_strtolower($target), '.csv')) {
                $path = (string) preg_replace('/\.csv$/', '', $path);

                return $label === '' ? '`'.$path.'`' : $label;
            }

            if (str_ends_with($path, '.md')) {
                $name = basename($path, '.md');
                $link = $names[mb_strtolower($name)] === 1 ? $name : mb_substr($path, 0, -3);

                return '[['.$link.($label === '' || $label === $name ? '' : '|'.$label).']]';
            }

            if ($bang === '!') {
                return '![['.$path.']]';
            }

            return '['.$label.']('.$this->relative(dirname($paths[$original]), $path).')';
        }, $contents);
    }

    private function resolve(string $directory, string $href): ?string
    {
        $segments = $directory === '.' ? [] : explode('/', $directory);

        foreach (explode('/', $href) as $segment) {
            if ($segment === '..') {
                if (array_pop($segments) === null) {
                    return null;
                }
            } elseif ($segment !== '.' && $segment !== '') {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }

    private function relative(string $from, string $to): string
    {
        $fromSegments = explode('/', $from);
        $toSegments = explode('/', $to);

        while ($fromSegments !== [] && $toSegments !== [] && $fromSegments[0] === $toSegments[0]) {
            array_shift($fromSegments);
            array_shift($toSegments);
        }

        $relative = str_repeat('../', count($fromSegments)).implode('/', $toSegments);

        return implode('/', array_map(rawurlencode(...), explode('/', $relative)));
    }

    /**
     * Notion exports callouts as `<aside>` blocks; Kite writes them as `> [!note]` quotes.
     */
    private function callouts(string $contents): string
    {
        return (string) preg_replace_callback('/^<aside>\n?(.*?)\n?<\/aside>$/ms', function (array $match): string {
            $lines = explode("\n", mb_trim($match[1]));

            return "> [!note]\n".implode("\n", array_map(fn (string $line): string => mb_rtrim('> '.$line), $lines));
        }, $contents);
    }

    /**
     * A database row page lists its properties as `Name: value` lines under its title; they
     * become frontmatter.
     *
     * @param  list<string>  $columns
     */
    private function properties(string $contents, array $columns): string
    {
        if (preg_match('/\A(# [^\n]*\n\n)((?:[^\n:]+: [^\n]*(?:\n|\z))+)/u', $contents, $block) !== 1) {
            return $contents;
        }

        $properties = [];

        foreach (explode("\n", mb_rtrim($block[2], "\n")) as $line) {
            [$key, $value] = explode(': ', $line, 2);

            if (! in_array($key, $columns, true)) {
                return $contents;
            }

            $properties[$key] = $this->value($key, mb_trim($value));
        }

        $rest = mb_ltrim(mb_substr($contents, mb_strlen($block[0])), "\n");
        $yaml = mb_rtrim(Yaml::dump($properties, 2, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_NULL_AS_EMPTY));

        return "---\n".preg_replace('/[ \t]+$/m', '', $yaml)."\n---\n".$block[1].$rest;
    }

    private function value(string $key, string $value): mixed
    {
        if ($value === '') {
            return null;
        }

        if ($value === 'Yes' || $value === 'No') {
            return $value === 'Yes';
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        if (in_array(mb_strtolower($key), self::LIST_PROPERTIES, true)) {
            return array_map(mb_trim(...), explode(',', $value));
        }

        if (preg_match('/^[A-Z][a-z]+ \d{1,2}, \d{4}$/', $value) === 1) {
            try {
                return CarbonImmutable::createFromFormat('F j, Y', $value)?->toDateString() ?? $value;
            } catch (Throwable) {
                return $value;
            }
        }

        return $value;
    }
}
