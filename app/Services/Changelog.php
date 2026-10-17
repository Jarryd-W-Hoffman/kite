<?php

declare(strict_types=1);

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * What changed in each version, from CHANGELOG.md. It ships with the app, so the Welcome tab can
 * show it offline, and the release workflow turns the same entry into the GitHub release notes.
 */
final readonly class Changelog
{
    /**
     * A version's heading, e.g. `## [0.2.0] - 2026-10-20`.
     */
    private const string HEADING = '/^##\s+\[?v?(?<version>[0-9][^\]\s]*)\]?/';

    /**
     * The Markdown of a version's entry, without its heading, or null when it has none.
     */
    public function notes(string $version): ?string
    {
        $path = config()->string('kite.changelog');

        if (! is_file($path)) {
            return null;
        }

        $version = mb_ltrim($version, 'v');
        $lines = [];
        $inside = false;

        foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
            if (str_starts_with($line, '## ')) {
                if ($inside) {
                    break;
                }

                $inside = preg_match(self::HEADING, $line, $match) === 1 && $match['version'] === $version;

                continue;
            }

            if ($inside) {
                $lines[] = $line;
            }
        }

        $notes = mb_trim(implode("\n", $lines));

        return $inside && $notes !== '' ? $notes : null;
    }

    /**
     * A version's entry as HTML, with any HTML in it escaped and unsafe links dropped.
     */
    public function html(string $version): ?string
    {
        $notes = $this->notes($version);

        if ($notes === null) {
            return null;
        }

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        return new MarkdownConverter($environment)->convert($notes)->getContent();
    }
}
