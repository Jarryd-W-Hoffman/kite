<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use Closure;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Replaces paragraphs that contain only a ![[note embed]] with the embedded note's content.
 */
final readonly class NoteEmbedTransformer
{
    /**
     * @param  Closure(string, string|null): (NoteEmbed|null)  $embed
     */
    public function __construct(private Closure $embed)
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
            $children = array_values(array_filter(
                [...$paragraph->children()],
                fn (Node $child): bool => ! $child instanceof Text || mb_trim($child->getLiteral()) !== '',
            ));

            $link = $children[0] ?? null;

            if (count($children) !== 1 || ! $link instanceof Link) {
                continue;
            }

            $class = $link->data->get('attributes/class', '');
            $target = $link->data->get('attributes/data-wiki-target', '');

            if (! is_string($class) || ! is_string($target) || ! str_contains($class, 'is-embed') || str_contains($class, 'is-unresolved')) {
                continue;
            }

            $embed = ($this->embed)($target, $link->getTitle());

            if ($embed instanceof NoteEmbed) {
                $paragraph->replaceWith($embed);
            }
        }
    }
}
