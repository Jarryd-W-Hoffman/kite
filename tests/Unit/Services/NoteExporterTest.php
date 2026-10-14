<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Services\NoteExporter;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Home.md' => "# Home\n\nSee [[Topic#Part]], [[Other]] and [[Missing]]. #tag\n\n![pic](pic.png) ![gone](missing.png)\n\n[web](https://example.com) [jump](#h-home)",
        'Topic.md' => "# Topic\n\n## Part",
        'Other.md' => '# Other',
    ]);
    file_put_contents($this->vault->path.'/pic.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    resolve(IndexVault::class)->handle($this->vault);
    $this->home = Note::query()->where('path', 'Home.md')->sole();
});

it('makes a standalone page with styles and inlined images', function (): void {
    $html = resolve(NoteExporter::class)->page($this->home);

    expect($html)
        ->toStartWith('<!DOCTYPE html>')
        ->toContain('<title>Home</title>')
        ->toContain('.kite-prose')
        ->toContain('<main class="kite-prose">')
        ->toContain('src="data:image/png;base64,')
        ->not->toContain('missing.png')
        ->not->toContain('/vaults/')
        ->toContain('<a href="https://example.com">web</a>')
        ->toContain('href="#h-home"')
        ->toContain('<span class="tag-link">#tag</span>');
});

it('points links at exported pages and flattens the rest', function (): void {
    $topic = Note::query()->where('path', 'Topic.md')->sole();

    $html = resolve(NoteExporter::class)->page($this->home, [$topic->id => 'topic.html'], 'index.html');

    expect($html)
        ->toContain('<a href="index.html">← Back</a>')
        ->toContain('<a class="wiki-link" href="topic.html#h-part" title="Part">Topic › Part</a>')
        ->toContain('<span class="wiki-link">Other</span>')
        ->toContain('<span class="wiki-link">Missing</span>');
});
