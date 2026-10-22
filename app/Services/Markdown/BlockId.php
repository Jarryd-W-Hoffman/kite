<?php

declare(strict_types=1);

namespace App\Services\Markdown;

/**
 * Block ids, as Obsidian writes them: `^my-id` at the end of a paragraph or list item (or on the
 * line after a table or quote) names that block, so `[[Note#^my-id]]` can link to it or embed it.
 */
final class BlockId
{
    /** An id at the end of a line, after a space or on its own. */
    public const string AT_LINE_END = '/(?:^|[ \t])\^([A-Za-z0-9-]+)[ \t]*$/';

    /**
     * The block id a link points to, as in `[[Note#^my-id]]`, or null for a heading or none.
     */
    public static function fromLink(?string $heading): ?string
    {
        return $heading !== null && preg_match('/^\^([A-Za-z0-9-]+)$/', mb_trim($heading), $match) === 1 ? $match[1] : null;
    }

    /**
     * The URL fragment for a link to a heading or block: `#my-heading` or `#^my-id`.
     */
    public static function fragment(string $heading): string
    {
        $id = self::fromLink($heading);

        return $id === null ? HeadingSlugger::slug($heading) : '^'.$id;
    }

    /**
     * The HTML id a block with this id gets.
     */
    public static function anchor(string $id): string
    {
        return 'b-'.$id;
    }
}
