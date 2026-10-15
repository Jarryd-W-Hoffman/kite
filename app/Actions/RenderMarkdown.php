<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\AttachmentResolver;
use App\Services\AttachmentTypes;
use App\Services\LinkResolver;
use App\Services\Markdown\CalloutTransformer;
use App\Services\Markdown\HeadingSlugger;
use App\Services\Markdown\MarkdownParser;
use App\Services\Markdown\MarkdownSection;
use App\Services\Markdown\MathBlock;
use App\Services\Markdown\MathBlockParser;
use App\Services\Markdown\MathInline;
use App\Services\Markdown\MathInlineParser;
use App\Services\Markdown\MathRenderer;
use App\Services\Markdown\NoteEmbed;
use App\Services\Markdown\NoteEmbedRenderer;
use App\Services\Markdown\NoteEmbedTransformer;
use App\Services\Markdown\OutlineCollector;
use App\Services\Markdown\RelativeLinkRewriter;
use App\Services\Markdown\RenderedMarkdown;
use App\Services\Markdown\TagInlineParser;
use App\Services\Markdown\TaskCheckboxLabeller;
use App\Services\Markdown\WikiLink;
use App\Services\Markdown\WikiLinkInlineParser;
use App\Services\Row;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

final readonly class RenderMarkdown
{
    private const string EMBED_TARGETS = '/\[\[([^\[\]\n|#]+)/u';

    private const int MAX_EMBED_DEPTH = 3;

    public function __construct(
        private MarkdownParser $parser,
        private LinkResolver $resolver,
        private AttachmentResolver $attachments,
        private AttachmentTypes $types,
        private VaultFilesystem $files,
        private MarkdownSection $sections,
    ) {
        //
    }

    /**
     * Render a note's markdown to safe HTML, resolving wiki links, embeds and relative paths inside the vault.
     */
    public function handle(Vault $vault, string $markdown, ?string $notePath = null): string
    {
        return $this->render($vault, $markdown, $notePath)->html;
    }

    /**
     * Render a note and also return its outline of anchored headings.
     */
    public function render(Vault $vault, string $markdown, ?string $notePath = null): RenderedMarkdown
    {
        $selfId = $notePath === null ? null : DB::table('notes')->where('vault_id', $vault->id)->where('path', $notePath)->value('id');

        return $this->renderDocument($vault, $markdown, $notePath, 0, is_int($selfId) ? [$selfId] : []);
    }

    /**
     * @param  list<int>  $embedding  Notes already being rendered further up, to stop embed loops.
     */
    private function renderDocument(Vault $vault, string $markdown, ?string $notePath, int $depth, array $embedding): RenderedMarkdown
    {
        $parsed = $this->parser->parse($markdown, $notePath ?? 'Untitled.md');
        $noteFolder = $notePath !== null && str_contains($notePath, '/') ? dirname($notePath) : '';

        $resolvedNotes = $this->resolver->resolve($vault, array_map(
            fn (WikiLink $link): string => $link->targetKey,
            $parsed->links,
        ));

        preg_match_all(self::EMBED_TARGETS, $parsed->body, $matches);

        $attachmentTargets = array_values(array_unique(array_filter(
            array_map(mb_trim(...), $matches[1]),
            $this->types->isAttachment(...),
        )));

        $resolvedAttachments = $this->attachments->resolve($vault, $attachmentTargets, $noteFolder);

        $fileUrl = fn (string $path): string => route('vault-files.show', ['vault' => $vault->id, 'path' => $path], absolute: false);

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addInlineParser(new WikiLinkInlineParser(
            fn (string $key): ?string => isset($resolvedNotes[$key])
                ? route('notes.show', ['vault' => $vault->id, 'note' => $resolvedNotes[$key]], absolute: false)
                : null,
            fn (string $target): ?array => $this->types->isAttachment($target) ? [
                'url' => isset($resolvedAttachments[$target]) ? $fileUrl($resolvedAttachments[$target]) : null,
                'image' => $this->types->isImage($target),
            ] : null,
        ), 200);
        $environment->addInlineParser(new TagInlineParser(
            fn (string $tag): string => route('vault-tags.show', ['vault' => $vault->id, 'tag' => $tag], absolute: false),
        ), 100);
        $environment->addEventListener(DocumentParsedEvent::class, new RelativeLinkRewriter(
            $noteFolder,
            fn (array $paths): array => DB::table('notes')
                ->where('vault_id', $vault->id)
                ->whereIn('path', $paths)
                ->get(['id', 'path'])
                ->mapWithKeys(fn (object $row): array => [
                    Row::string($row, 'path') => route('notes.show', ['vault' => $vault->id, 'note' => Row::int($row, 'id')], absolute: false),
                ])
                ->all(),
            $fileUrl,
        ));

        $environment->addBlockStartParser(MathBlockParser::startParser(), 80);
        $environment->addInlineParser(new MathInlineParser(), 150);
        $environment->addRenderer(MathInline::class, new MathRenderer());
        $environment->addRenderer(MathBlock::class, new MathRenderer());
        $environment->addEventListener(DocumentParsedEvent::class, new CalloutTransformer(), 5);
        $environment->addEventListener(DocumentParsedEvent::class, new TaskCheckboxLabeller());

        $environment->addRenderer(NoteEmbed::class, new NoteEmbedRenderer());
        $environment->addEventListener(DocumentParsedEvent::class, new NoteEmbedTransformer(
            fn (string $target, ?string $heading): ?NoteEmbed => $this->embed($vault, $resolvedNotes[WikiLink::keyFor($target)] ?? null, $heading, $depth, $embedding),
        ), 10);

        $outline = new OutlineCollector($parsed->bodyStartLine, assignIds: $depth === 0);
        $environment->addEventListener(DocumentParsedEvent::class, $outline);

        $html = new MarkdownConverter($environment)->convert($parsed->body)->getContent();

        return new RenderedMarkdown($html, $outline->outline());
    }

    /**
     * @param  list<int>  $embedding
     */
    private function embed(Vault $vault, ?int $noteId, ?string $heading, int $depth, array $embedding): ?NoteEmbed
    {
        $note = $noteId === null ? null : Note::query()->where('vault_id', $vault->id)->find($noteId, ['id', 'path', 'title', 'locked']);

        if (! $note instanceof Note || ! $this->files->exists($vault, $note->path)) {
            return null;
        }

        $title = $heading === null || $heading === '' ? $note->title : "{$note->title} › {$heading}";
        $url = route('notes.show', ['vault' => $vault->id, 'note' => $note->id], absolute: false)
            .($heading === null || $heading === '' ? '' : '#'.HeadingSlugger::slug($heading));

        if (in_array($note->id, $embedding, true)) {
            return new NoteEmbed($title, $url, '', NoteEmbed::LOOP);
        }

        if ($depth >= self::MAX_EMBED_DEPTH) {
            return new NoteEmbed($title, $url, '', NoteEmbed::TOO_DEEP);
        }

        if ($note->locked) {
            return new NoteEmbed($title, $url, '', NoteEmbed::LOCKED);
        }

        $contents = $this->files->read($vault, $note->path);

        if ($heading !== null && $heading !== '') {
            $contents = $this->sections->extract($contents, $heading);

            if ($contents === null) {
                return new NoteEmbed($title, $url, '', NoteEmbed::MISSING_SECTION);
            }
        }

        $html = $this->renderDocument($vault, $contents, $note->path, $depth + 1, [...$embedding, $note->id])->html;

        return new NoteEmbed($title, $url, $html);
    }
}
