<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use Closure;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Point standard markdown links and images with relative paths at the vault's notes and files.
 */
final readonly class RelativeLinkRewriter
{
    /**
     * @param  Closure(list<string>): array<string, string>  $noteUrls  Map vault-relative note paths to note URLs.
     * @param  Closure(string): string  $fileUrl  URL that serves a vault-relative file.
     */
    public function __construct(
        private string $noteFolder,
        private Closure $noteUrls,
        private Closure $fileUrl,
    ) {
        //
    }

    public function __invoke(DocumentParsedEvent $event): void
    {
        $targets = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if (($node instanceof Image || $node instanceof Link) && ! $node->data->has('attributes/class')) {
                $path = self::vaultPath($this->noteFolder, $node->getUrl());

                if ($path !== null) {
                    $targets[] = [$node, $path];
                }
            }
        }

        $notePaths = array_values(array_filter(
            array_map(fn (array $target): string => $target[1], $targets),
            fn (string $path): bool => preg_match('/\.md$/i', $path) === 1,
        ));

        $noteUrls = $notePaths === [] ? [] : ($this->noteUrls)($notePaths);

        foreach ($targets as [$node, $path]) {
            if (preg_match('/\.md$/i', $path) === 1) {
                $url = $noteUrls[$path] ?? null;

                $node->setUrl($url ?? '#');
                $node->data->set('attributes/class', 'wiki-link'.($url === null ? ' is-unresolved' : ''));
                $node->data->set('attributes/data-wiki-target', (string) preg_replace('/\.md$/i', '', basename($path)));

                continue;
            }

            $node->setUrl(($this->fileUrl)($path));
            $node->data->set('attributes/class', $node instanceof Image ? 'attachment-embed' : 'attachment-link');
        }
    }

    /**
     * Resolve a relative URL against a folder inside the vault, or null if it is not a vault path.
     */
    public static function vaultPath(string $folder, string $url): ?string
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1) {
            return null;
        }

        $path = rawurldecode((string) preg_replace('/[?#].*$/', '', $url));
        $segments = $folder === '' ? [] : explode('/', mb_trim($folder, '/'));

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);
            } elseif ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }

        return $segments === [] ? null : implode('/', $segments);
    }
}
