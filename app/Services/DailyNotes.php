<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Where daily notes live and how they are named.
 */
final readonly class DailyNotes
{
    public function pathFor(CarbonInterface $date): string
    {
        $folder = $this->folder();

        return ($folder === '' ? '' : $folder.'/').$date->format($this->format()).'.md';
    }

    /**
     * The date a note path represents, or null when it is not a daily note.
     */
    public function dateOf(string $path): ?CarbonImmutable
    {
        $folder = $this->folder();
        $prefix = $folder === '' ? '' : $folder.'/';

        if (! str_starts_with($path, $prefix) || ! str_ends_with($path, '.md')) {
            return null;
        }

        $name = mb_substr($path, mb_strlen($prefix), -3);

        if (! CarbonImmutable::canBeCreatedFromFormat($name, '!'.$this->format())) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!'.$this->format(), $name);

        return $date instanceof CarbonImmutable && $date->format($this->format()) === $name ? $date : null;
    }

    public function template(): ?string
    {
        $template = config('kite.daily.template');

        return is_string($template) && mb_trim($template) !== '' ? mb_trim($template) : null;
    }

    private function folder(): string
    {
        $folder = config('kite.daily.folder');

        return is_string($folder) ? mb_trim($folder, '/') : 'Daily';
    }

    private function format(): string
    {
        $format = config('kite.daily.format');

        return is_string($format) && $format !== '' ? $format : 'Y-m-d';
    }
}
