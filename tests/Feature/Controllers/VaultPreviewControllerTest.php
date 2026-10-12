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
