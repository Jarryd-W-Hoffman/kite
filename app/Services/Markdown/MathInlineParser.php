<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * `$x^2$` becomes inline math. The dollars must hug the formula and the closing one can't be
 * followed by a digit, so prices like "$5 and $10" stay as text. An escaped \$ never reaches
 * this parser.
 */
final readonly class MathInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\$(?![\s$])((?:[^$\\\\\n]|\\\\.)+?)(?<!\s)\$(?!\d)');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$tex] = $inlineContext->getSubMatches();

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new MathInline($tex));

        return true;
    }
}
