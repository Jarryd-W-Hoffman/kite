<?php

declare(strict_types=1);

use App\Services\Markdown\MarkdownSection;

it('extracts a section up to the next heading of the same or higher level', function (): void {
    $markdown = implode("\n", [
        '# Title',
        'Intro',
        '## Setup **steps**',
        'Install it.',
        '### Details',
        'More.',
        '```',
        '## Not a heading',
        '```',
        '## Usage',
        'Use it.',
    ]);

    $sections = new MarkdownSection();

    expect($sections->extract($markdown, 'Setup steps'))->toBe("## Setup **steps**\nInstall it.\n### Details\nMore.\n```\n## Not a heading\n```\n")
        ->and($sections->extract($markdown, 'usage'))->toBe("## Usage\nUse it.\n")
        ->and($sections->extract($markdown, 'Not a heading'))->toBeNull()
        ->and($sections->extract($markdown, 'Missing'))->toBeNull();
});

it('extracts the block a block id names', function (): void {
    $markdown = implode("\n", [
        '# Title',
        'A paragraph over',
        'two lines. ^para',
        '',
        '- First item',
        '- Second item ^item',
        '- Third item',
        '',
        '| a | b |',
        '| - | - |',
        '| 1 | 2 |',
        '',
        '^table',
        '',
        '```',
        'Not a block ^code',
        '```',
        'Right under ^under',
        '^own',
    ]);

    $sections = new MarkdownSection();

    expect($sections->extract($markdown, '^para'))->toBe("A paragraph over\ntwo lines.\n")
        ->and($sections->extract($markdown, ' ^item '))->toBe("- Second item\n")
        ->and($sections->extract($markdown, '^table'))->toBe("| a | b |\n| - | - |\n| 1 | 2 |\n")
        ->and($sections->extract($markdown, '^own'))->toBe("Right under ^under\n")
        ->and($sections->extract($markdown, '^code'))->toBeNull()
        ->and($sections->extract($markdown, '^missing'))->toBeNull()
        ->and($sections->extract("^lonely\nText", '^lonely'))->toBeNull();
});
