<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\RenderMarkdown;
use App\Models\Note;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\HTMLElement;
use Dom\Node;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Turns a note into a standalone HTML page: styles and images inlined, links to other notes
 * pointing at their exported pages (when they are part of the export) or reduced to plain text.
 */
final readonly class NoteExporter
{
    /** Images larger than this are left out rather than bloating the page. */
    private const int MAX_INLINE_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private RenderMarkdown $renderMarkdown,
        private VaultFilesystem $files,
    ) {
        //
    }

    /**
     * @param  array<int, string>  $pages  Note ids that are exported alongside, mapped to their relative URL.
     * @param  string|null  $home  Relative URL of the page to link back to, if any.
     */
    public function page(Note $note, array $pages = [], ?string $home = null): string
    {
        $vault = $note->vault;
        $rendered = $this->renderMarkdown->render($vault, $this->files->read($vault, $note->path), $note->path);
        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><head></head><body></body></html>', LIBXML_NOERROR);

        $document->documentElement?->setAttribute('lang', str_replace('_', '-', app()->getLocale()));
        $head = $document->head;
        $body = $document->body;

        throw_if(! $head instanceof HTMLElement || ! $body instanceof HTMLElement, InvalidArgumentException::class, 'Could not build the export page.');

        $charset = $document->createElement('meta');
        $charset->setAttribute('charset', 'utf-8');

        $viewport = $document->createElement('meta');
        $viewport->setAttribute('name', 'viewport');
        $viewport->setAttribute('content', 'width=device-width, initial-scale=1');

        $generator = $document->createElement('meta');
        $generator->setAttribute('name', 'generator');
        $generator->setAttribute('content', 'Kite');

        $title = $document->createElement('title');
        $title->textContent = $note->title;

        $style = $document->createElement('style');
        $style->textContent = File::get(resource_path('css/export.css'));

        $head->append($charset, $viewport, $generator, $title, $style);

        if ($home !== null) {
            $nav = $document->createElement('nav');
            $nav->setAttribute('class', 'kite-published');
            $back = $document->createElement('a');
            $back->setAttribute('href', $home);
            $back->textContent = '← Back';
            $nav->append($back);
            $body->append($nav);
        }

        $main = $document->createElement('main');
        $main->setAttribute('class', 'kite-prose');
        $main->innerHTML = $rendered->html;

        $body->append($main);

        $this->rewriteLinks($document, $main, $note, $pages);
        $this->inlineImages($main, $note);

        return $document->saveHtml();
    }

    /**
     * @param  array<int, string>  $pages
     */
    private function rewriteLinks(HTMLDocument $document, Element $root, Note $note, array $pages): void
    {
        $notePrefix = '/vaults/'.$note->vault_id.'/notes/';

        foreach (iterator_to_array($root->querySelectorAll('a[href]')) as $link) {
            $href = (string) $link->getAttribute('href');
            $class = (string) $link->getAttribute('class');

            if (str_starts_with($href, $notePrefix)) {
                $id = (int) mb_substr($href, mb_strlen($notePrefix));

                if (isset($pages[$id])) {
                    $link->setAttribute('href', $pages[$id].$this->headingFragment($href));
                    $link->removeAttribute('data-wiki-target');

                    continue;
                }
            }

            // A link to a heading in this note.
            if (str_starts_with($href, '#') && $href !== '#') {
                $link->setAttribute('href', $this->headingFragment($href));

                continue;
            }

            if (str_starts_with($href, '/') || $href === '#' || str_contains($class, 'wiki-link') || str_contains($class, 'tag-link') || str_contains($class, 'attachment-link')) {
                $this->unwrap($document, $link, $class);
            }
        }
    }

    /**
     * The app links to headings by slug (#part); on the page their ids are prefixed (#h-part).
     */
    private function headingFragment(string $href): string
    {
        if (! str_contains($href, '#')) {
            return '';
        }

        $slug = mb_substr((string) mb_strstr($href, '#'), 1);

        return $slug === '' ? '' : '#'.(str_starts_with($slug, 'h-') ? $slug : 'h-'.$slug);
    }

    /**
     * Swap an app-only link for a span with the same text and class.
     */
    private function unwrap(HTMLDocument $document, Element $link, string $class): void
    {
        $span = $document->createElement('span');
        $span->setAttribute('class', mb_trim(str_replace(['is-unresolved', 'attachment-link'], ['', 'wiki-link'], $class)) ?: 'wiki-link');

        while ($link->firstChild instanceof Node) {
            $span->append($link->firstChild);
        }

        $link->replaceWith($span);
    }

    private function inlineImages(Element $root, Note $note): void
    {
        $filePrefix = '/vaults/'.$note->vault_id.'/files/';

        foreach (iterator_to_array($root->querySelectorAll('img[src]')) as $image) {
            $src = (string) $image->getAttribute('src');

            if (! str_starts_with($src, $filePrefix)) {
                continue;
            }

            $path = rawurldecode(mb_substr($src, mb_strlen($filePrefix)));

            try {
                $absolute = $this->files->absolutePath($note->vault, $path);
            } catch (InvalidArgumentException) {
                $image->remove();

                continue;
            }

            if (! File::isFile($absolute) || File::size($absolute) > self::MAX_INLINE_BYTES) {
                $image->remove();

                continue;
            }

            $image->setAttribute('src', 'data:'.(File::mimeType($absolute) ?: 'application/octet-stream').';base64,'.base64_encode(File::get($absolute)));
        }
    }
}
