<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use Closure;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Renders inline #tags as links to their tag page, using the same boundaries as tag indexing.
 */
final readonly class TagInlineParser implements InlineParserInterface
{
    /**
     * @param  Closure(string): string  $urlForTag
     */
    public function __construct(private Closure $urlForTag)
    {
        //
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('#([\p{L}\p{N}_\-\/]*[\p{L}_\-\/][\p{L}\p{N}_\-\/]*)');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $previous = $inlineContext->getCursor()->peek(-1);

        if ($previous !== null && preg_match('/[\p{L}\p{N}_&\/#\]\)(\'"`=]/u', $previous) === 1) {
            return false;
        }

        [$name] = $inlineContext->getSubMatches();
        $tag = mb_strtolower(mb_trim($name, '/'));

        if ($tag === '') {
            return false;
        }

        $link = new Link(($this->urlForTag)($tag));
        $link->appendChild(new Text('#'.$name));
        $link->data->set('attributes/class', 'tag-link');
        $link->data->set('attributes/data-tag', $tag);

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild($link);

        return true;
    }
}
