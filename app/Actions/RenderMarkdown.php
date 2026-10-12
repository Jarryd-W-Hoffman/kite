<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\LinkResolver;
use App\Services\Markdown\MarkdownParser;
use App\Services\Markdown\WikiLink;
use App\Services\Markdown\WikiLinkInlineParser;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

final readonly class RenderMarkdown
{
    public function __construct(
        private MarkdownParser $parser,
        private LinkResolver $resolver,
    ) {
        //
    }

    /**
     * Render a note's markdown to safe HTML, turning wiki links into links to the notes they resolve to.
     */
    public function handle(Vault $vault, string $markdown): string
    {
        $parsed = $this->parser->parse($markdown, 'Untitled.md');

        $resolved = $this->resolver->resolve($vault, array_map(
            fn (WikiLink $link): string => $link->targetKey,
            $parsed->links,
        ));

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addInlineParser(new WikiLinkInlineParser(
            fn (string $key): ?string => isset($resolved[$key])
                ? route('notes.show', ['vault' => $vault->id, 'note' => $resolved[$key]], absolute: false)
                : null,
        ), 200);

        return new MarkdownConverter($environment)->convert($parsed->body)->getContent();
    }
}
