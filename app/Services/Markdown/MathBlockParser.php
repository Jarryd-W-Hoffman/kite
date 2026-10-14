<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * A `$$` line opens display math and the next line ending in `$$` closes it; `$$ x $$` on one
 * line works too.
 */
final class MathBlockParser extends AbstractBlockContinueParser
{
    private readonly MathBlock $block;

    /** @var list<string> */
    private array $lines = [];

    /** The parser hands over what's left of the opening line, which tryStart already took. */
    private bool $skipOpeningLine = true;

    public function __construct(private bool $closed)
    {
        $this->block = new MathBlock();
    }

    public static function startParser(): BlockStartParserInterface
    {
        return new class implements BlockStartParserInterface
        {
            public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
            {
                if ($cursor->isIndented() || ! str_starts_with($cursor->getRemainder(), '$$')) {
                    return BlockStart::none();
                }

                $rest = mb_trim(mb_substr($cursor->getRemainder(), 2));
                $closed = $rest !== '' && str_ends_with($rest, '$$');
                $parser = new MathBlockParser($closed);

                if ($closed) {
                    $parser->addContent(mb_substr($rest, 0, -2));
                } elseif ($rest !== '') {
                    $parser->addContent($rest);
                }

                $cursor->advanceToEnd();

                return BlockStart::of($parser)->at($cursor);
            }
        };
    }

    public function getBlock(): MathBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($this->closed) {
            return BlockContinue::none();
        }

        $line = mb_rtrim($cursor->getRemainder());

        if (str_ends_with($line, '$$')) {
            $this->addContent(mb_substr($line, 0, -2));
            $this->closed = true;

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    public function addLine(string $line): void
    {
        if ($this->skipOpeningLine) {
            $this->skipOpeningLine = false;

            return;
        }

        $this->addContent($line);
    }

    public function addContent(string $line): void
    {
        $this->lines[] = $line;
    }

    public function closeBlock(): void
    {
        $this->block->tex = mb_trim(implode("\n", $this->lines));
    }
}
