<?php

declare(strict_types=1);

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
