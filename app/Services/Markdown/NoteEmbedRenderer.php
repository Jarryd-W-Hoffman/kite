<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Stringable;

final readonly class NoteEmbedRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        throw_unless($node instanceof NoteEmbed, InvalidArgumentException::class, 'Expected a note embed.');

        $title = new HtmlElement('a', [
            'class' => 'wiki-link note-embed-title',
            'href' => $node->url,
            'data-wiki-target' => $node->title,
        ], e($node->title));

        $body = match ($node->state) {
            NoteEmbed::LOOP => new HtmlElement('p', ['class' => 'note-embed-notice'], e(__('This note is already embedded above.'))),
            NoteEmbed::TOO_DEEP => new HtmlElement('p', ['class' => 'note-embed-notice'], e(__('Embeds are nested too deeply to show here.'))),
            NoteEmbed::MISSING_SECTION => new HtmlElement('p', ['class' => 'note-embed-notice'], e(__('That section was not found.'))),
            default => new HtmlElement('div', ['class' => 'note-embed-body'], $node->html),
        };

        return new HtmlElement('div', ['class' => 'note-embed', 'data-state' => $node->state], [$title, $body]);
    }
}
