<?php

declare(strict_types=1);

namespace App\Actions;

use Symfony\Component\Yaml\Yaml;

final readonly class UpdateFrontmatter
{
    private const string BLOCK = '/\A---[ \t]*\n(.*?)\n?(?:---|\.\.\.)[ \t]*(?:\n|\z)/s';

    /**
     * Replace a note's frontmatter with these properties, keeping the body as it is. No
     * properties removes the block; a note without one gains one.
     *
     * @param  array<string, mixed>  $properties
     */
    public function handle(string $contents, array $properties): string
    {
        $body = preg_match(self::BLOCK, $contents, $match) === 1
            ? mb_substr($contents, mb_strlen($match[0]))
            : $contents;

        if ($properties === []) {
            return mb_ltrim($body, "\n");
        }

        $yaml = Yaml::dump($properties, 2, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_NULL_AS_EMPTY);

        // Empty values are written as a bare "key:", without the space YAML leaves after it.
        $yaml = (string) preg_replace('/[ \t]+$/m', '', $yaml);

        return "---\n".$yaml."---\n".$body;
    }
}
