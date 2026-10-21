<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use Closure;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

final readonly class WikiLinkInlineParser implements InlineParserInterface
{
    /**
     * @param  Closure(string): (string|null)  $urlForKey
     * @param  Closure(string): (array{url: string|null, image: bool}|null)|null  $attachmentFor
     */
    public function __construct(
        private Closure $urlForKey,
        private ?Closure $attachmentFor = null,
    ) {
        //
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('!?\[\[([^\[\]\n]+?)\]\]');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$inner] = $inlineContext->getSubMatches();

        $inner = str_replace('\|', '|', $inner);
        $isEmbed = str_starts_with($inlineContext->getFullMatch(), '!');

        $parts = explode('|', $inner, 2);
        $destination = explode('#', $parts[0], 2);
        $target = mb_trim($destination[0]);
        $heading = isset($destination[1]) ? mb_trim($destination[1]) : '';
        $alias = isset($parts[1]) ? mb_trim($parts[1]) : '';

        if ($target === '' && $heading === '') {
            return false;
        }

        $attachment = $this->attachmentFor instanceof Closure && $target !== '' ? ($this->attachmentFor)($target) : null;

        $node = match (true) {
            $target === '' => $this->headingLink($heading, $alias),
            $attachment === null => $this->noteLink($target, $heading, $alias, $isEmbed),
            default => $this->attachmentLink($target, $alias, $isEmbed, $attachment),
        };

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild($node);

        return true;
    }

    private function headingLink(string $heading, string $alias): Link
    {
        $link = new Link('#'.BlockId::fragment($heading));
        $link->appendChild(new Text($alias !== '' ? $alias : $heading));
        $link->data->set('attributes/class', 'wiki-link heading-link');

        return $link;
    }

    private function noteLink(string $target, string $heading, string $alias, bool $isEmbed): Link
    {
        $url = ($this->urlForKey)(WikiLink::keyFor($target));

        if ($url !== null && $heading !== '') {
            $url .= '#'.BlockId::fragment($heading);
        }

        $label = $alias !== '' ? $alias : ($heading !== '' ? "{$target} › {$heading}" : $target);

        $link = new Link($url ?? '#', null, $heading !== '' ? $heading : null);
        $link->appendChild(new Text($label));
        $link->data->set('attributes/class', 'wiki-link'.($url === null ? ' is-unresolved' : '').($isEmbed ? ' is-embed' : ''));
        $link->data->set('attributes/data-wiki-target', $target);

        return $link;
    }

    /**
     * @param  array{url: string|null, image: bool}  $attachment
     */
    private function attachmentLink(string $target, string $alias, bool $isEmbed, array $attachment): Image|Link
    {
        $isSize = preg_match('/^(\d{1,5})(?:x(\d{1,5}))?$/', $alias, $size) === 1;

        if ($isEmbed && $attachment['image'] && $attachment['url'] !== null) {
            $image = new Image($attachment['url'], $isSize || $alias === '' ? basename($target) : $alias);
            $image->data->set('attributes/class', 'attachment-embed');

            if ($isSize) {
                $image->data->set('attributes/width', $size[1]);

                if (isset($size[2])) {
                    $image->data->set('attributes/height', $size[2]);
                }
            }

            return $image;
        }

        $link = new Link($attachment['url'] ?? '#');
        $link->appendChild(new Text($alias !== '' && ! $isSize ? $alias : basename($target)));
        $link->data->set('attributes/class', 'attachment-link'.($attachment['url'] === null ? ' is-unresolved' : ''));
        $link->data->set('attributes/data-attachment', $target);

        return $link;
    }
}
