<?php

declare(strict_types=1);

namespace App\Services\Markdown;

final readonly class ParsedNote
{
    /**
     * @param  array<string, mixed>  $frontmatter
     * @param  list<WikiLink>  $links
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $frontmatter,
        public array $links,
        public array $tags,
        public int $wordCount,
        public int $bodyStartLine = 0,
    ) {
        //
    }
}
