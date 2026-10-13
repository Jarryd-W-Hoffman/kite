<?php

declare(strict_types=1);

it('renders a markdown preview', function (): void {
    $vault = vaultWithFiles();

    $this->postJson(route('vault-preview.store', $vault), ['contents' => '**bold** [[Missing]]'])
        ->assertOk()
        ->assertJsonPath('html', "<p><strong>bold</strong> <a class=\"wiki-link is-unresolved\" data-wiki-target=\"Missing\" href=\"#\">Missing</a></p>\n");

    $this->postJson(route('vault-preview.store', $vault), ['contents' => null])
        ->assertJsonPath('html', '');
});

it('requires contents', function (): void {
    $this->postJson(route('vault-preview.store', vaultWithFiles()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('contents');
});

it('resolves relative paths against the note being previewed', function (): void {
    $vault = vaultWithFiles(['Notes/pic.png' => 'png']);

    $this->postJson(route('vault-preview.store', $vault), ['contents' => '![x](pic.png)', 'path' => 'Notes/Draft.md'])
        ->assertJsonPath('html', '<p><img class="attachment-embed" src="'.route('vault-files.show', ['vault' => $vault->id, 'path' => 'Notes/pic.png'], absolute: false).'" alt="x" /></p>'."\n");
});
