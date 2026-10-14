<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\AttachmentResolver;
use App\Services\AttachmentTypes;
use App\Services\LinkResolver;
use App\Services\Markdown\MarkdownParser;
use App\Services\Markdown\RelativeLinkRewriter;
use App\Services\Markdown\WikiLink;
use App\Services\Markdown\WikiLinkInlineParser;
use App\Services\Row;
use Illuminate\Support\Facades\DB;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

final readonly class RenderMarkdown
{
    private const string EMBED_TARGETS = '/\[\[([^\[\]\n|#]+)/u';

    public function __construct(
        private MarkdownParser $parser,
        private LinkResolver $resolver,
        private AttachmentResolver $attachments,
        private AttachmentTypes $types,
    ) {
        //
    }

    /**
     * Render a note's markdown to safe HTML, resolving wiki links, embeds and relative paths inside the vault.
     */
    public function handle(Vault $vault, string $markdown, ?string $notePath = null): string
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

        return new MarkdownConverter($environment)->convert($parsed->body)->getContent();
    }
}
