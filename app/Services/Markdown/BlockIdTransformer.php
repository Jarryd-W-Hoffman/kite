<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Hide block ids (`^my-id`) from the rendered note and give their blocks an anchor, so links to
 * a block land on it. An id at the end of a paragraph names the paragraph, or the list item it
 * is in; an id on a paragraph of its own names the block before it, such as a table.
 */
final readonly class BlockIdTransformer
{
    public function __construct(private bool $assignIds = true)
    {
        //
    }

    public function __invoke(DocumentParsedEvent $event): void
    {
        $paragraphs = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Paragraph) {
                $paragraphs[] = $node;
            }
        }

        foreach ($paragraphs as $paragraph) {
            $last = $paragraph->lastChild();

            if (! $last instanceof Text || preg_match(BlockId::AT_LINE_END, $last->getLiteral(), $match) !== 1) {
                continue;
            }

            $last->setLiteral(mb_rtrim((string) preg_replace(BlockId::AT_LINE_END, '', $last->getLiteral())));

            if ($last->getLiteral() === '') {
                $before = $last->previous();
                $last->detach();

                if ($before instanceof Newline) {
                    $before->detach();
                }
            }

            $block = $paragraph->parent() instanceof ListItem ? $paragraph->parent() : $paragraph;

            if (! $paragraph->firstChild() instanceof Node) {
                $block = $paragraph->previous();
                $paragraph->detach();
            }

            if ($this->assignIds && $block instanceof AbstractBlock) {
                $block->data->set('attributes/id', BlockId::anchor($match[1]));
            }
        }
    }
}
