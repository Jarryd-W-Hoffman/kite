<?php

declare(strict_types=1);

namespace App\Services\Markdown;

/**
 * GitHub-style heading anchors: lowercase, punctuation stripped, spaces to hyphens, duplicates numbered.
 */
final class HeadingSlugger
{
    /**
     * @var array<string, int>
     */
    private array $seen = [];

    public static function slug(string $text): string
    {
        $slug = mb_strtolower(mb_trim($text));
        $slug = (string) preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $slug);
        $slug = (string) preg_replace('/\s/u', '-', $slug);

        return $slug === '' ? 'section' : $slug;
    }

    public function unique(string $text): string
    {
        $slug = self::slug($text);
        $count = $this->seen[$slug] ?? 0;

        $this->seen[$slug] = $count + 1;

        return $count === 0 ? $slug : "{$slug}-{$count}";
    }
}
