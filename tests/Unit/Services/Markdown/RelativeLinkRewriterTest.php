<?php

declare(strict_types=1);

use App\Services\Markdown\RelativeLinkRewriter;

it('resolves relative urls against a vault folder', function (string $folder, string $url, ?string $expected): void {
    expect(RelativeLinkRewriter::vaultPath($folder, $url))->toBe($expected);
})->with([
    ['', 'img.png', 'img.png'],
    ['Notes', './img.png', 'Notes/img.png'],
    ['Notes/Deep', '../assets/My%20Photo.png?v=1#x', 'Notes/assets/My Photo.png'],
    ['Notes', '../../escape.png', null],
    ['', '..', null],
    ['', '.', null],
    ['', 'https://example.com/a.png', null],
    ['', 'mailto:hi@example.com', null],
    ['', '#heading', null],
    ['', '/absolute.png', null],
    ['', '', null],
]);
