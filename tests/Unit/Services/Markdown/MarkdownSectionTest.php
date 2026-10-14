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
