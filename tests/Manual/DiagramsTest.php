<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

/*
 * The manual's Mermaid diagrams, drawn the way Kite's preview draws them, so a typo in one is
 * caught before GitHub shows it as an error. Runs with the screenshots:
 * `php artisan test --testsuite=Manual`.
 */

it('draws every diagram in the manual', function (): void {
    $files = [];

    foreach (glob(base_path('docs/manual/*.md')) as $page) {
        preg_match_all('/```mermaid\n(.*?)```/s', (string) file_get_contents($page), $matches);

        if ($matches[1] !== []) {
            $files[basename($page)] = implode("\n\n", array_map(fn (string $diagram): string => "```mermaid\n{$diagram}```", $matches[1]));
        }
    }

    $vault = vaultWithFiles($files);
    resolve(IndexVault::class)->handle($vault);

    foreach ($files as $path => $contents) {
        $expected = mb_substr_count($contents, '```mermaid');
        $page = visit(route('notes.show', [$vault, Note::query()->where('path', $path)->sole()]));
        $page->script("localStorage.setItem('kite:view-mode', 'preview'); document.querySelector('[aria-label=Preview]:not([data-state=on])')?.click()");
        $page->wait(3);

        $rendered = $page->script("document.querySelectorAll('[data-test=note-preview] figure svg').length");
        $failed = $page->script("document.querySelectorAll('[data-test=note-preview] .mermaid-diagram.is-invalid').length");

        expect($failed)->toBe(0, "A diagram in {$path} has an error.")->and($rendered)->toBe($expected);
    }
});
