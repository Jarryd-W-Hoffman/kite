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
            $term = $this->matchIn($line, $terms);

            if ($term !== null) {
                return ['line' => $number, 'term' => $term, 'context' => mb_trim($line)];
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

        $pattern = $this->pattern($terms);

        if ($pattern === null || $this->matchIn($line, $terms) === null) {
            return null;
        }

        $replaced = false;

        $linked = (string) preg_replace_callback($pattern, function (array $match) use (&$replaced, $targetName): string {
            $text = $match['term'] ?? null;

            if ($replaced || $text === null) {
                return $match[0];
            }

            $replaced = true;

            return mb_strtolower($text) === mb_strtolower($targetName) ? "[[{$text}]]" : "[[{$targetName}|{$text}]]";
        }, $line, flags: PREG_UNMATCHED_AS_NULL);

        $all = preg_split('/(?<=\n)/', $contents) ?: [];
        $index = $lineNumber - 1;
        $ending = str_ends_with($all[$index], "\r\n") ? "\r\n" : (str_ends_with($all[$index], "\n") ? "\n" : '');

        $all[$index] = $linked.$ending;

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
     */
    private function matchIn(string $line, array $terms): ?string
    {
        $pattern = $this->pattern($terms);

        if ($pattern === null) {
            return null;
        }

        preg_match_all($pattern, $line, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        foreach ($matches as $match) {
            if (isset($match['term'])) {
                return $match['term'];
            }
        }

        return null;
    }

    /**
     * Protected spans (links, code, URLs) are matched first so terms inside them are skipped.
     *
     * @param  list<string>  $terms
     */
    private function pattern(array $terms): ?string
    {
        $terms = array_values(array_filter(array_map(mb_trim(...), $terms), fn (string $term): bool => $term !== ''));

        if ($terms === []) {
            return null;
        }

        usort($terms, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $alternatives = implode('|', array_map(fn (string $term): string => preg_quote($term, '/'), $terms));

        return '/\[\[.*?\]\]|`[^`]*`|\[[^\]]*\]\([^)]*\)|https?:\/\/\S+|(?<![\p{L}\p{N}_])(?<term>'.$alternatives.')(?![\p{L}\p{N}_])/iu';
    }
}
