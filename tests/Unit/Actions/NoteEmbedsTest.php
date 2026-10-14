<?php

declare(strict_types=1);

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Actions\RenderMarkdown;
use App\Models\Note;
use App\Models\Vault;
use App\Services\Markdown\NoteEmbedRenderer;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Renderer\ChildNodeRendererInterface;

function embedVault(array $files): Vault
{
    $vault = vaultWithFiles($files);
    resolve(IndexVault::class)->handle($vault);
    resolve(IndexAttachments::class)->handle($vault);

    return $vault;
}

function renderNote(Vault $vault, string $path): string
{
    return resolve(RenderMarkdown::class)->handle($vault, (string) file_get_contents($vault->path.'/'.$path), $path);
}

it('embeds whole notes and sections on their own line', function (): void {
    $vault = embedVault([
        'Home.md' => "# Home\n\n![[Guide]]\n\n![[Guide#Usage|ignored alias]]\n\nInline ![[Guide]] stays a link.",
        'Docs/Guide.md' => "# Guide\nIntro with [[Home]] and ![img](pic.png)\n\n## Usage\nUse it.",
        'Docs/pic.png' => 'png',
    ]);
    $guide = Note::query()->where('path', 'Docs/Guide.md')->sole();
    $url = route('notes.show', ['vault' => $vault->id, 'note' => $guide->id], absolute: false);

    $html = renderNote($vault, 'Home.md');

    expect($html)
        ->toContain('<div class="note-embed" data-state="rendered"><a class="wiki-link note-embed-title" href="'.$url.'" data-wiki-target="Guide">Guide</a><div class="note-embed-body"><h1>Guide</h1>')
        ->toContain('src="'.route('vault-files.show', ['vault' => $vault->id, 'path' => 'Docs/pic.png'], absolute: false).'"')
        ->toContain('href="'.$url.'#usage" data-wiki-target="Guide › Usage">Guide › Usage</a><div class="note-embed-body"><h2>Usage</h2>')
        ->toContain('<p>Inline <a class="wiki-link is-embed"')
        ->toContain('<h1 id="h-home">Home</h1>')
        ->not->toContain('id="h-guide"');
});

it('stops embed loops, self embeds and runaway nesting', function (): void {
    $vault = embedVault([
        'A.md' => "# A\n\n![[B]]",
        'B.md' => "# B\n\n![[A]]",
        'Self.md' => "# Self\n\n![[Self]]",
        'One.md' => '![[Two]]',
        'Two.md' => '![[Three]]',
        'Three.md' => '![[Four]]',
        'Four.md' => '![[Five]]',
        'Five.md' => 'bottom',
    ]);

    expect(renderNote($vault, 'A.md'))
        ->toContain('data-state="rendered"')
        ->toContain('<div class="note-embed" data-state="loop">')
        ->toContain('This note is already embedded above.')
        ->and(renderNote($vault, 'Self.md'))->toContain('data-state="loop"')
        ->and(renderNote($vault, 'One.md'))
        ->toContain('data-state="too-deep"')
        ->toContain('Embeds are nested too deeply to show here.')
        ->not->toContain('bottom');
});

it('reports missing sections and leaves unresolved or deleted embeds as links', function (): void {
    $vault = embedVault([
        'Home.md' => "![[Guide#Nope]]\n\n![[Ghost]]\n\n![[Gone]]",
        'Guide.md' => '# Guide',
        'Gone.md' => 'soon deleted',
    ]);
    unlink($vault->path.'/Gone.md');

    expect(renderNote($vault, 'Home.md'))
        ->toContain('data-state="missing-section"')
        ->toContain('That section was not found.')
        ->toContain('<a class="wiki-link is-unresolved is-embed" data-wiki-target="Ghost" href="#">Ghost</a>')
        ->toContain('<a class="wiki-link is-embed" data-wiki-target="Gone"');
});

it('renders unsaved drafts without a note path', function (): void {
    $vault = embedVault(['Guide.md' => 'Guide body']);

    expect(resolve(RenderMarkdown::class)->handle($vault, '![[Guide]]'))->toContain('<p>Guide body</p>');
});

it('only renders note embed nodes', function (): void {
    new NoteEmbedRenderer()->render(
        new Paragraph(),
        Mockery::mock(ChildNodeRendererInterface::class),
    );
})->throws(InvalidArgumentException::class, 'Expected a note embed.');
