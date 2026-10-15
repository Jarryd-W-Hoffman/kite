<?php

declare(strict_types=1);

namespace App\Services\Flashcards;

/**
 * One card found in a note: a question (front) and its answer (back), both Markdown.
 */
final readonly class Card
{
    /**
     * @param  'basic'|'reversed'|'multiline'|'cloze'  $kind
     */
    public function __construct(
        public string $kind,
        public string $front,
        public string $back,
        public int $line,
    ) {
        //
    }

    /**
     * What a card's review schedule is kept under. It depends only on the question, so the
     * schedule survives editing the answer, or moving or renaming the note; changing the
     * question makes it a new card.
     */
    public function key(): string
    {
        return mb_substr(hash('sha256', $this->kind."\0".mb_trim((string) preg_replace('/\s+/u', ' ', $this->front))), 0, 20);
    }
}
