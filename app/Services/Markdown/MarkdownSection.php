<?php

declare(strict_types=1);

namespace App\Services\Markdown;

/**
 * Extracts the part of a note under a heading, up to the next heading of the same or higher level,
 * or the block a block id (`^my-id`) names.
 */
final readonly class MarkdownSection
{
    public function extract(string $markdown, string $heading): ?string
    {
        $blockId = BlockId::fromLink($heading);

        if ($blockId !== null) {
            return $this->block($markdown, $blockId);
        }

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

    /**
     * The paragraph or list item ending in ` ^id`, or the block before an id on a line of its own,
     * without the id.
     */
    private function block(string $markdown, string $id): ?string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $marker = '/(?:^|[ \t])\^'.preg_quote($id, '/').'[ \t]*$/';
        $fence = null;
        $at = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $fenceMarker) === 1) {
                $fence = $fence === null ? $fenceMarker[1][0] : ($fenceMarker[1][0] === $fence ? null : $fence);
            }

            if ($fence === null && preg_match($marker, $line) === 1) {
                $at = $index;

                break;
            }
        }

        if ($at === null) {
            return null;
        }

        $isBlank = fn (int $index): bool => mb_trim($lines[$index]) === '';
        $end = $at;

        // An id on a line of its own names the block above it, even across a blank line.
        if (mb_trim($lines[$at]) === '^'.$id) {
            $end = $at - 1;

            while ($end >= 0 && $isBlank($end)) {
                $end--;
            }

            if ($end < 0) {
                return null;
            }
        }

        $start = $end;

        if (preg_match('/^\s*(?:[-*+]|\d+[.)])\s/', $lines[$end]) !== 1) {
            // A paragraph starts after a blank line, a heading or the end of a code block.
            while ($start > 0 && ! $isBlank($start - 1) && preg_match('/^(?:#{1,6}\s|\s{0,3}(?:`{3,}|~{3,}))/', $lines[$start - 1]) !== 1) {
                $start--;
            }
        }

        $block = array_slice($lines, $start, $end - $start + 1);
        $last = count($block) - 1;
        $block[$last] = mb_rtrim((string) preg_replace($marker, '', $block[$last]));

        return implode("\n", $block)."\n";
    }
}
