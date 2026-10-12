<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use Closure;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

final readonly class WikiLinkInlineParser implements InlineParserInterface
{
    /**
     * @param  Closure(string): (string|null)  $urlForKey
     */
    public function __construct(private Closure $urlForKey)
    {
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

        if ($target === '') {
            return false;
        }

        $url = ($this->urlForKey)(WikiLink::keyFor($target));

        $label = $alias !== '' ? $alias : ($heading !== '' ? "{$target} › {$heading}" : $target);

        $link = new Link($url ?? '#', null, $heading !== '' ? $heading : null);
        $link->appendChild(new Text($label));
        $link->data->set('attributes/class', 'wiki-link'.($url === null ? ' is-unresolved' : '').($isEmbed ? ' is-embed' : ''));
        $link->data->set('attributes/data-wiki-target', $target);

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild($link);

        return true;
    }
}
