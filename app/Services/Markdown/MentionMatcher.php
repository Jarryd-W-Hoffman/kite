<?php

declare(strict_types=1);

namespace App\Services\Markdown;

/**
 * Finds plain-text mentions of a note (outside links and code) and turns them into wiki links.
 */
final readonly class MentionMatcher
{
    /**
     * @param  list<string>  $terms
     * @return array{line: int, term: string, context: string}|null
     */
    public function find(string $contents, array $terms): ?array
    {
        foreach ($this->lines($contents) as $number => $line) {
            $match = $this->matchIn($line, $terms);

            if ($match !== null) {
                return ['line' => $number, 'term' => $match[0], 'context' => mb_trim($line)];
            }
        }

        return null;
    }

    /**
     * Replace the first unlinked mention on the given line with a wiki link to the target.
     *
     * @param  list<string>  $terms
     */
    public function link(string $contents, int $lineNumber, array $terms, string $targetName): ?string
    {
        $lines = $this->lines($contents);
        $line = $lines[$lineNumber] ?? null;

        if ($line === null) {
            return null;
        }

        $match = $this->matchIn($line, $terms);

        if ($match === null) {
            return null;
        }

        [$text, $offset] = $match;
        $link = mb_strtolower($text) === mb_strtolower($targetName) ? "[[{$text}]]" : "[[{$targetName}|{$text}]]";

        $all = preg_split('/(?<=\n)/', $contents) ?: [];
        $index = $lineNumber - 1;
        $ending = str_ends_with($all[$index], "\r\n") ? "\r\n" : (str_ends_with($all[$index], "\n") ? "\n" : '');

        $all[$index] = mb_substr($line, 0, $offset).$link.mb_substr($line, $offset + mb_strlen($text)).$ending;

        return implode('', $all);
    }

    /**
     * Lines of the note keyed by 1-based line number, skipping frontmatter and fenced code.
     *
     * @return array<int, string>
     */
    private function lines(string $contents): array
    {
        $lines = preg_split('/\r?\n/', $contents) ?: [];
        $result = [];
        $fence = null;
        $inFrontmatter = ($lines[0] ?? null) === '---';

        foreach ($lines as $index => $line) {
            if ($inFrontmatter) {
                $inFrontmatter = $index === 0 || ! in_array(mb_rtrim($line), ['---', '...'], true);

                continue;
            }

            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $matches) === 1) {
                $marker = $matches[1];
                $fence = $fence === null ? $marker : ($marker[0] === $fence[0] && mb_strlen($marker) >= mb_strlen($fence) ? null : $fence);

                continue;
            }

            if ($fence === null) {
                $result[$index + 1] = $line;
            }
        }

        return $result;
    }

    /**
     * Find the first term occurring outside wiki links, markdown links, inline code and URLs.
     *
     * @param  list<string>  $terms
     * @return array{0: string, 1: int}|null The matched text and its byte offset.
     */
    private function matchIn(string $line, array $terms): ?array
    {
        $terms = array_values(array_filter($terms, fn (string $term): bool => mb_trim($term) !== ''));

        if ($terms === []) {
            return null;
        }

        usort($terms, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $alternatives = implode('|', array_map(fn (string $term): string => preg_quote(mb_trim($term), '/'), $terms));
        $pattern = '/\[\[.*?\]\]|`[^`]*`|\[[^\]]*\]\([^)]*\)|https?:\/\/\S+|(?<![\p{L}\p{N}_])(?<term>'.$alternatives.')(?![\p{L}\p{N}_])/iu';

        preg_match_all($pattern, $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        foreach ($matches as $match) {
            if (isset($match['term'][0])) {
                return [$match['term'][0], $match['term'][1]];
            }
        }

        return null;
    }
}
