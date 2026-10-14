<?php

declare(strict_types=1);

use App\Services\Markdown\HeadingSlugger;

it('creates github style slugs', function (string $text, string $slug): void {
    expect(HeadingSlugger::slug($text))->toBe($slug);
})->with([
    ['Hello World', 'hello-world'],
    ['  Café: Ünïcode & Co.  ', 'café-ünïcode--co'],
    ['snake_case-ok', 'snake_case-ok'],
    ['!!!', 'section'],
]);

it('numbers duplicate slugs', function (): void {
    $slugger = new HeadingSlugger();

    expect([$slugger->unique('Intro'), $slugger->unique('Intro'), $slugger->unique('intro')])->toBe(['intro', 'intro-1', 'intro-2']);
});
