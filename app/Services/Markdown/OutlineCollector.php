<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\StringContainerHelper;

/**
 * Give every heading a stable anchor id and record the note's outline.
 */
final class OutlineCollector
{
    /**
     * @var list<array{level: int, text: string, slug: string, line: int}>
     */
    private array $outline = [];

    public function __construct(private readonly int $lineOffset = 0)
    {
        //
    }

    public function __invoke(DocumentParsedEvent $event): void
    {
        $slugger = new HeadingSlugger();
        $this->outline = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if (! $node instanceof Heading) {
                continue;
            }

            $text = mb_trim(StringContainerHelper::getChildText($node));
            $slug = $slugger->unique($text);

            $node->data->set('attributes/id', 'h-'.$slug);

            $this->outline[] = [
                'level' => $node->getLevel(),
                'text' => $text,
                'slug' => $slug,
                'line' => ($node->getStartLine() ?? 1) + $this->lineOffset,
            ];
        }
    }

    /**
     * @return list<array{level: int, text: string, slug: string, line: int}>
     */
    public function outline(): array
    {
        return $this->outline;
    }
}
