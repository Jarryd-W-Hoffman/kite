<?php

declare(strict_types=1);

namespace App\Services\Markdown;

final readonly class RenderedMarkdown
{
    /**
     * @param  list<array{level: int, text: string, slug: string, line: int}>  $outline
     */
    public function __construct(
        public string $html,
        public array $outline,
    ) {
        //
    }
}
