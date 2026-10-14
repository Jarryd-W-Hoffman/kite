<?php

declare(strict_types=1);

namespace App\Services\Markdown;

/**
 * Extracts the part of a note under a heading, up to the next heading of the same or higher level.
 */
final readonly class MarkdownSection
{
    public function extract(string $markdown, string $heading): ?string
    {
        $slug = HeadingSlugger::slug(str_replace(['[[', ']]'], '', $heading));
        $lines = preg_split('/\R/', $markdown) ?: [];
        $section = null;
        $level = 0;
        $fence = null;

        foreach ($lines as $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $marker) === 1) {
                $fence = $fence === null ? $marker[1][0] : ($marker[1][0] === $fence ? null : $fence);
            }

            $isHeading = $fence === null && preg_match('/^(#{1,6})[ \t]+(.+?)[ \t#]*$/u', $line, $match) === 1;

            if ($section !== null) {
                if ($isHeading && mb_strlen($match[1]) <= $level) {
                    break;
                }

                $section[] = $line;

                continue;
            }

            if ($isHeading && HeadingSlugger::slug(str_replace(['[[', ']]', '*', '_', '`'], '', $match[2])) === $slug) {
                $section = [$line];
                $level = mb_strlen($match[1]);
            }
        }

        return $section === null ? null : mb_rtrim(implode("\n", $section))."\n";
    }
}
