<?php

declare(strict_types=1);

use App\Actions\RenderMarkdown;

it('leaves tags made only of slashes as plain text', function (string $markdown): void {
    $html = resolve(RenderMarkdown::class)->handle(vaultWithFiles(), $markdown);

    expect($html)->toContain($markdown)
        ->not->toContain('tag-link');
})->with([
    'single slash' => 'see #/ here',
    'many slashes' => 'see #/// here',
]);
