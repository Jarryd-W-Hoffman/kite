<?php

declare(strict_types=1);

namespace App\Services\Flashcards;

/**
 * Finds the cards written in a note's text:
 *
 *  - `Question :: Answer` on one line;
 *  - `Question ::: Answer`, which also asks the other way round;
 *  - question lines, then a line with just `?`, then answer lines, ending at a blank line;
 *  - a line with `==highlighted==` parts: each highlight is hidden in turn (cloze).
 *
 * Code blocks and inline code are never read as cards, so `Foo::bar()` stays code.
 */
final readonly class CardParser
{
    private const string FENCE = '/^\s{0,3}(`{3,}|~{3,})/';

    private const string LIST_MARKER = '/^\s*(?:[-*+]|\d+[.)])\s+(?:\[[ xX]\]\s+)?/';

    private const string CLOZE = '/==(?=\S)(.+?)(?<=\S)==/u';

    /**
     * @return list<Card>
     */
    public function parse(string $markdown, int $firstLine = 1): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $markdown));
        $cards = [];
        $fence = null;
        $block = [];

        foreach ($lines as $index => $line) {
            $number = $firstLine + $index;

            if (preg_match(self::FENCE, $line, $marker) === 1) {
                $fence = $fence === null ? $marker[1][0] : ($marker[1][0] === $fence ? null : $fence);
                $block = [];

                continue;
            }

            if ($fence !== null) {
                continue;
            }

            if (mb_trim($line) === '') {
                array_push($cards, ...$this->multiline($block));
                $block = [];

                continue;
            }

            $block[] = [$number, $line];
        }

        array_push($cards, ...$this->multiline($block));

        foreach ($this->singleLines($lines, $firstLine) as $card) {
            $cards[] = $card;
        }

        usort($cards, fn (Card $a, Card $b): int => $a->line <=> $b->line);

        return $cards;
    }

    /**
     * A block of lines with a `?` line in it: what's above asks, what's below answers.
     *
     * @param  list<array{0: int, 1: string}>  $block
     * @return list<Card>
     */
    private function multiline(array $block): array
    {
        $separator = array_search('?', array_map(fn (array $line): string => mb_trim($line[1]), $block), true);

        if ($separator === false || $separator === 0 || $separator === count($block) - 1) {
            return [];
        }

        $text = fn (array $lines): string => implode("\n", array_column($lines, 1));

        return [new Card('multiline', $text(array_slice($block, 0, $separator)), $text(array_slice($block, $separator + 1)), $block[0][0])];
    }

    /**
     * @param  list<string>  $lines
     * @return list<Card>
     */
    private function singleLines(array $lines, int $firstLine): array
    {
        $cards = [];
        $fence = null;

        foreach ($lines as $index => $line) {
            if (preg_match(self::FENCE, $line, $marker) === 1) {
                $fence = $fence === null ? $marker[1][0] : ($marker[1][0] === $fence ? null : $fence);

                continue;
            }

            if ($fence !== null || mb_trim($line) === '?') {
                continue;
            }

            $number = $firstLine + $index;
            $text = mb_trim((string) preg_replace(self::LIST_MARKER, '', $line));
            $separator = $this->separator($text);

            if ($separator !== null) {
                [$position, $length] = $separator;
                $front = mb_trim(mb_substr($text, 0, $position));
                $back = mb_trim(mb_substr($text, $position + $length));

                if ($front !== '' && $back !== '') {
                    $cards[] = new Card($length === 3 ? 'reversed' : 'basic', $front, $back, $number);

                    if ($length === 3) {
                        $cards[] = new Card('reversed', $back, $front, $number);
                    }
                }

                continue;
            }

            array_push($cards, ...$this->clozes($text, $number));
        }

        return $cards;
    }

    /**
     * Where `::` or `:::` splits a line, outside inline code: [position, length], or null.
     *
     * @return array{0: int, 1: int}|null
     */
    private function separator(string $text): ?array
    {
        $masked = (string) preg_replace_callback('/`[^`]*`/u', fn (array $code): string => str_repeat('x', mb_strlen($code[0])), $text);

        if (preg_match('/\s?(:{2,3})\s?/u', $masked, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        // Positions from preg are in bytes; the masked text has the same characters before them.
        $position = mb_strlen(mb_substr($masked, 0, $match[1][1]));

        return [$position, mb_strlen($match[1][0])];
    }

    /**
     * A card for each `==highlight==` on a line (outside inline code), hiding that one and showing
     * the others as plain text.
     *
     * @return list<Card>
     */
    private function clozes(string $text, int $line): array
    {
        // Odd segments are inline code, left as they are.
        $segments = preg_split('/(`[^`]*`)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $count = 0;

        foreach ($segments as $index => $segment) {
            $count += $index % 2 === 0 ? preg_match_all(self::CLOZE, $segment) : 0;
        }

        $cards = [];

        for ($target = 0; $target < $count; $target++) {
            $seen = 0;
            $render = function (bool $answer) use ($segments, $target, &$seen): string {
                $seen = 0;
                $out = '';

                foreach ($segments as $index => $segment) {
                    $out .= $index % 2 === 1 ? $segment : (string) preg_replace_callback(self::CLOZE, function (array $match) use ($target, $answer, &$seen): string {
                        $isTarget = $seen++ === $target;

                        return match (true) {
                            ! $isTarget => $match[1],
                            $answer => "**{$match[1]}**",
                            default => '[…]',
                        };
                    }, $segment);
                }

                return $out;
            };

            $cards[] = new Card('cloze', $render(false), $render(true), $line);
        }

        return $cards;
    }
}
