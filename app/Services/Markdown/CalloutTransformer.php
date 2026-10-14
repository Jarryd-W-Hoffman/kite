<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Turns Obsidian-style callouts into styled boxes: a quote whose first line is `[!type] Title`
 * (`[!note]`, `[!tip]`, `[!warning]`…) gets the callout's class and a title row.
 */
final readonly class CalloutTransformer
{
    private const string MARKER = '/^\[!([A-Za-z][\w-]*)\]([+-]?)[ \t]*(.*)$/u';

    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof BlockQuote) {
                $this->transform($node);
            }
        }
    }

    private function transform(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();
        $first = $paragraph instanceof Paragraph ? $paragraph->firstChild() : null;

        if (! $paragraph instanceof Paragraph || ! $first instanceof Text || preg_match(self::MARKER, $first->getLiteral(), $match) !== 1) {
            return;
        }

        $type = mb_strtolower($match[1]);
        $title = new Paragraph();
        $title->data->set('attributes/class', 'callout-title');

        if (mb_trim($match[3]) !== '') {
            $title->appendChild(new Text($match[3]));
        }

        // Anything else on the marker's line (e.g. **bold** in the title) belongs to the title too.
        $next = $first->next();
        $first->detach();

        while ($next instanceof Node && ! $next instanceof Newline) {
            $following = $next->next();
            $title->appendChild($next);
            $next = $following;
        }

        $next?->detach();

        if (! $title->hasChildren()) {
            $title->appendChild(new Text(ucfirst($type)));
        }

        if (! $paragraph->hasChildren()) {
            $paragraph->detach();
        }

        $quote->prependChild($title);
        $quote->data->set('attributes/class', 'callout');
        $quote->data->set('attributes/data-callout', $type);

        if ($match[2] !== '') {
            $quote->data->set('attributes/data-fold', $match[2] === '-' ? 'closed' : 'open');
        }
    }
}
