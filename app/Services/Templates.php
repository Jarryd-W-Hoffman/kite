<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vault;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Note templates: the notes in the templates folder, and the placeholders filled in when a note is
 * made from one.
 */
final readonly class Templates
{
    public function folder(): string
    {
        $folder = config('kite.templates.folder');

        return is_string($folder) ? mb_trim($folder, '/ ') : 'Templates';
    }

    /**
     * @return list<array{id: int, path: string, name: string}>
     */
    public function all(Vault $vault): array
    {
        $folder = $this->folder();

        if ($folder === '') {
            return [];
        }

        return DB::table('notes')
            ->where('vault_id', $vault->id)
            ->where('path', 'like', str_replace(['%', '_'], ['\%', '\_'], $folder).'/%')
            ->orderByRaw('path COLLATE NOCASE')
            ->get(['id', 'path'])
            ->map(fn (object $row): array => [
                'id' => Row::int($row, 'id'),
                'path' => Row::string($row, 'path'),
                'name' => mb_substr(Row::string($row, 'path'), mb_strlen($folder) + 1, -3),
            ])
            ->values()
            ->all();
    }

    public function contains(string $path): bool
    {
        $folder = $this->folder();

        return $folder !== '' && str_starts_with($path, $folder.'/');
    }

    /**
     * Fill in placeholders. Unknown ones are left as they are, so a template can still show `{{x}}`.
     *
     * Supported: {{title}}, {{date}}, {{time}}, {{datetime}}, {{date:FORMAT}} (a PHP date format),
     * {{yesterday}}, {{tomorrow}}, {{folder}} and {{vault}}.
     *
     * @param  array{title: string, date?: CarbonInterface, folder?: string, vault?: string}  $context
     */
    public function render(string $contents, array $context): string
    {
        $date = CarbonImmutable::instance($context['date'] ?? now());
        $now = CarbonImmutable::now();

        $values = [
            'title' => $context['title'],
            'date' => $date->toDateString(),
            'time' => $now->format('H:i'),
            'datetime' => $date->toDateString().' '.$now->format('H:i'),
            'yesterday' => $date->subDay()->toDateString(),
            'tomorrow' => $date->addDay()->toDateString(),
            'folder' => $context['folder'] ?? '',
            'vault' => $context['vault'] ?? '',
        ];

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z]+)(?::([^}]+))?\s*\}\}/i',
            function (array $match) use ($values, $date): string {
                $name = mb_strtolower($match[1]);

                if ($name === 'date' && isset($match[2]) && mb_trim($match[2]) !== '') {
                    return $date->translatedFormat(mb_trim($match[2]));
                }

                return $values[$name] ?? $match[0];
            },
            $contents,
        );
    }
}
