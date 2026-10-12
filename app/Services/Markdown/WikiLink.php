<?php

declare(strict_types=1);

namespace App\Services\Markdown;

final readonly class WikiLink
{
    public function __construct(
        public string $target,
        public string $targetKey,
        public ?string $heading,
        public ?string $alias,
        public bool $isEmbed,
        public int $line,
    ) {
        //
    }

    /**
     * Normalise a link target or note path into the key used for link resolution.
     */
    public static function keyFor(string $target): string
    {
        $key = mb_trim(str_replace('\\', '/', $target));
        $key = (string) preg_replace('#^(\./|/)+#', '', $key);
        $key = (string) preg_replace('/\.md$/i', '', $key);
        $key = (string) preg_replace('/\s+/u', ' ', $key);

        return mb_strtolower($key);
    }
}
