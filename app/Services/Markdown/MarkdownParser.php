<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use DateTimeInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class MarkdownParser
{
    private const string LINK_PATTERN = '/(!?)\[\[([^\[\]\n]+?)\]\]/u';

    private const string TAG_PATTERN = '/(?<![\p{L}\p{N}_&\/#\]\)(\'"`=])#([\p{L}\p{N}_\-\/]*[\p{L}_\-\/][\p{L}\p{N}_\-\/]*)/u';

    public function parse(string $contents, string $path): ParsedNote
    {
        $contents = $this->normalise($contents);

        [$frontmatter, $frontmatterLines] = $this->extractFrontmatter($contents);

        $lines = explode("\n", $contents);
        $body = implode("\n", array_slice($lines, $frontmatterLines));
        $searchable = $this->maskCode($lines, $frontmatterLines);

        return new ParsedNote(
            title: $this->title($frontmatter, $searchable, $path),
            body: $body,
            frontmatter: $frontmatter,
            links: $this->links($searchable),
            tags: $this->tags($frontmatter, $searchable),
            wordCount: $this->wordCount($searchable),
            bodyStartLine: $frontmatterLines,
        );
    }

    private function wordCount(string $searchable): int
    {
        $count = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’_-]*/u', $searchable);

        return $count === false ? 0 : $count;
    }

    private function normalise(string $contents): string
    {
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-8');
        }

        $contents = str_replace(["\r\n", "\r"], "\n", $contents);

        return (string) preg_replace('/\A\x{FEFF}/u', '', $contents);
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function extractFrontmatter(string $contents): array
    {
        if (preg_match('/\A---[ \t]*\n(.*?)\n?(?:---|\.\.\.)[ \t]*(?:\n|\z)/s', $contents, $matches) !== 1) {
            return [[], 0];
        }

        $lineCount = mb_substr_count($matches[0], "\n") + (str_ends_with($matches[0], "\n") ? 0 : 1);

        try {
            $parsed = Yaml::parse($matches[1], Yaml::PARSE_DATETIME);
        } catch (ParseException) {
            return [[], $lineCount];
        }

        if (! is_array($parsed)) {
            return [[], $lineCount];
        }

        $frontmatter = [];

        foreach ($parsed as $key => $value) {
            $frontmatter[(string) $key] = $this->normaliseValue($value);
        }

        return [$frontmatter, $lineCount];
    }

    private function normaliseValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format(DATE_ATOM);
        }

        if (is_array($value)) {
            return array_map($this->normaliseValue(...), $value);
        }

        return $value;
    }

    /**
     * Blank out frontmatter, fenced code blocks and inline code while keeping line numbers intact.
     *
     * @param  list<string>  $lines
     */
    private function maskCode(array $lines, int $frontmatterLines): string
    {
        $fence = null;

        foreach ($lines as $index => $line) {
            if ($index < $frontmatterLines) {
                $lines[$index] = '';

                continue;
            }

            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $matches) === 1) {
                $marker = $matches[1];

                if ($fence === null) {
                    $fence = $marker;
                } elseif ($marker[0] === $fence[0] && mb_strlen($marker) >= mb_strlen($fence)) {
                    $fence = null;
                }

                $lines[$index] = '';

                continue;
            }

            $lines[$index] = $fence === null
                ? (string) preg_replace('/(`+)(?:(?!\1).)+?\1/u', '', $line)
                : '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     */
    private function title(array $frontmatter, string $searchable, string $path): string
    {
        $title = $frontmatter['title'] ?? null;

        if (is_string($title) && mb_trim($title) !== '') {
            return mb_trim($title);
        }

        if (preg_match('/^#[ \t]+(.+?)[ \t#]*$/mu', $searchable, $matches) === 1) {
            return mb_trim(str_replace(['[[', ']]'], '', $matches[1]));
        }

        return (string) preg_replace('/\.md$/i', '', basename(str_replace('\\', '/', $path)));
    }

    /**
     * @return list<WikiLink>
     */
    private function links(string $searchable): array
    {
        preg_match_all(self::LINK_PATTERN, $searchable, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        preg_match_all('/\n/', $searchable, $newlines, PREG_OFFSET_CAPTURE);

        $newlineOffsets = array_column($newlines[0], 1);

        $links = [];

        foreach ($matches as $match) {
            $inner = str_replace('\|', '|', $match[2][0]);

            [$destination, $alias] = $this->splitOnce($inner, '|');
            [$target, $heading] = $this->splitOnce($destination, '#');

            $target = mb_trim($target);

            if ($target === '' || $this->isAttachment($target)) {
                continue;
            }

            $alias = $alias === null ? null : mb_trim($alias);
            $heading = $heading === null ? null : mb_trim($heading);

            $links[] = new WikiLink(
                target: $target,
                targetKey: WikiLink::keyFor($target),
                heading: $heading === '' ? null : $heading,
                alias: $alias === '' ? null : $alias,
                isEmbed: $match[1][0] === '!',
                line: $this->lineAt($newlineOffsets, $match[0][1]),
            );
        }

        return $links;
    }

    /**
     * @param  non-empty-string  $separator
     * @return array{0: string, 1: string|null}
     */
    private function splitOnce(string $value, string $separator): array
    {
        $parts = explode($separator, $value, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    /**
     * @param  list<int>  $newlineOffsets
     */
    private function lineAt(array $newlineOffsets, int $offset): int
    {
        $line = 1;

        foreach ($newlineOffsets as $newlineOffset) {
            if ($newlineOffset >= $offset) {
                break;
            }

            $line++;
        }

        return $line;
    }

    private function isAttachment(string $target): bool
    {
        return preg_match('/\.[a-z0-9]{1,5}$/i', $target) === 1
            && preg_match('/\.md$/i', $target) !== 1;
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     * @return list<string>
     */
    private function tags(array $frontmatter, string $searchable): array
    {
        $declared = $frontmatter['tags'] ?? $frontmatter['tag'] ?? [];

        $candidates = match (true) {
            is_string($declared) => preg_split('/[\s,]+/u', $declared, flags: PREG_SPLIT_NO_EMPTY) ?: [],
            is_array($declared) => array_filter($declared, is_scalar(...)),
            default => [],
        };

        $searchableWithoutLinks = (string) preg_replace(self::LINK_PATTERN, '', $searchable);

        preg_match_all(self::TAG_PATTERN, $searchableWithoutLinks, $matches);

        $tags = [];

        foreach ([...$candidates, ...$matches[1]] as $candidate) {
            $tag = mb_strtolower(mb_trim(mb_ltrim((string) $candidate, '#'), " \t/"));

            if ($tag !== '') {
                $tags[$tag] = true;
            }
        }

        $tags = array_map(strval(...), array_keys($tags));

        sort($tags);

        return $tags;
    }
}
