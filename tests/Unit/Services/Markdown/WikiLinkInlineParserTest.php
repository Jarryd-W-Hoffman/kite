<?php

declare(strict_types=1);

use App\Actions\RenderMarkdown;

it('leaves wiki links without a target or heading as plain text', function (string $markdown): void {
    $html = resolve(RenderMarkdown::class)->handle(vaultWithFiles(), $markdown);

    expect($html)->toContain($markdown)
        ->not->toContain('<a');
})->with([
    'blank' => '[[ ]]',
    'bare hash' => '[[#]]',
    'alias only' => '[[ |Alias]]',
]);
