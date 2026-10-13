<?php

declare(strict_types=1);

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Actions\RenderMarkdown;
use App\Models\Note;

it('renders github flavoured markdown without frontmatter', function (): void {
    $vault = vaultWithFiles();

    $html = resolve(RenderMarkdown::class)->handle($vault, "---\ntitle: Hidden\n---\n# Hi\n\n- [x] done\n\n| a |\n|---|\n| b |\n\n~~gone~~");

    expect($html)->not->toContain('Hidden')
        ->toContain('<h1>Hi</h1>')
        ->toContain('type="checkbox"')
        ->toContain('<table>')
        ->toContain('<del>gone</del>');
});

it('escapes raw html and unsafe links', function (): void {
    $html = resolve(RenderMarkdown::class)->handle(vaultWithFiles(), "<script>alert(1)</script>\n\n[x](javascript:alert(1)) <img src=x onerror=alert(1)>");

    expect($html)->not->toContain('<script>')
        ->not->toContain('<img')
        ->not->toContain('javascript:')
        ->toContain('&lt;script&gt;');
});

it('turns wiki links into note links and marks unresolved ones', function (): void {
    $vault = vaultWithFiles(['Topic.md' => '# Topic']);

    resolve(IndexVault::class)->handle($vault);

    $topic = Note::query()->sole();
    $url = route('notes.show', ['vault' => $vault->id, 'note' => $topic->id], absolute: false);

    $html = resolve(RenderMarkdown::class)->handle($vault, implode("\n\n", [
        'See [[Topic]] and [[topic#Intro|the intro]].',
        'Missing: [[Nowhere <b>]] and [[Topic#Part]] ![[Topic]]',
        '`[[Code]]` [[#Only heading]]',
        '| col |',
        '|---|',
        '| [[Topic\|aliased]] |',
    ]));

    expect($html)
        ->toContain('<a class="wiki-link" data-wiki-target="Topic" href="'.$url.'">Topic</a>')
        ->toContain('<a class="wiki-link" data-wiki-target="topic" href="'.$url.'" title="Intro">the intro</a>')
        ->toContain('<a class="wiki-link is-unresolved" data-wiki-target="Nowhere &lt;b&gt;" href="#">Nowhere &lt;b&gt;</a>')
        ->toContain('>Topic › Part</a>')
        ->toContain('class="wiki-link is-embed"')
        ->toContain('<code>[[Code]]</code>')
        ->toContain('[[#Only heading]]')
        ->toContain('>aliased</a>');
});

it('embeds images and links other attachments', function (): void {
    $vault = vaultWithFiles([
        'Notes/Trip.md' => '# Trip',
        'Notes/beach.png' => 'png',
        'attachments/beach.png' => 'png',
        'files/Guide.pdf' => 'pdf',
    ]);
    resolve(IndexVault::class)->handle($vault);
    resolve(IndexAttachments::class)->handle($vault);

    $file = fn (string $path): string => route('vault-files.show', ['vault' => $vault->id, 'path' => $path], absolute: false);

    $html = resolve(RenderMarkdown::class)->handle($vault, implode("\n\n", [
        '![[beach.png]] ![[beach.png|Sunset]] ![[beach.png|320x200]] ![[beach.png|64]]',
        '[[Guide.pdf]] ![[Guide.pdf|the guide]] ![[Guide.pdf|300]] ![[missing.png]]',
        '![relative](beach.png) [up](../files/Guide.pdf) [note](Trip.md) [ghost](Nope.md) [web](https://example.com)',
    ]), 'Notes/Trip.md');

    expect($html)
        ->toContain('<img class="attachment-embed" src="'.$file('Notes/beach.png').'" alt="beach.png" />')
        ->toContain('alt="Sunset"')
        ->toContain('width="320" height="200"')
        ->toContain('width="64" src=')
        ->toContain('<a class="attachment-link" data-attachment="Guide.pdf" href="'.$file('files/Guide.pdf').'">Guide.pdf</a>')
        ->toContain('>the guide</a>')
        ->toContain('<a class="attachment-link is-unresolved" data-attachment="missing.png" href="#">missing.png</a>')
        ->toContain('<img class="attachment-embed" src="'.$file('Notes/beach.png').'" alt="relative" />')
        ->toContain('<a class="attachment-link" href="'.$file('files/Guide.pdf').'">up</a>')
        ->toContain('class="wiki-link" data-wiki-target="Trip" href="'.route('notes.show', ['vault' => $vault->id, 'note' => Note::query()->sole()->id], absolute: false).'">note</a>')
        ->toContain('class="wiki-link is-unresolved" data-wiki-target="Nope" href="#">ghost</a>')
        ->toContain('href="https://example.com">web</a>');
});
