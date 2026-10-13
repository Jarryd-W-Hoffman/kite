<?php

declare(strict_types=1);

use App\Services\Markdown\MentionMatcher;

it('finds the first plain text mention outside links and code', function (): void {
    $contents = implode("\n", [
        '---',
        'title: Kite in frontmatter',
        '---',
        '[[Kite]] and [Kite](Kite.md) and `Kite` and https://kite.dev/Kite',
        '```',
        'Kite in a fence',
        '```',
        'Kites are not Kite-app, but kite is.',
    ]);

    expect(new MentionMatcher()->find($contents, ['Kite']))->toBe([
        'line' => 8,
        'term' => 'Kite',
        'context' => 'Kites are not Kite-app, but kite is.',
    ]);
});

it('prefers the longest matching term and ignores empty terms', function (): void {
    expect(new MentionMatcher()->find('Building the Kite App today', ['Kite', 'Kite App', ' ']))->toMatchArray(['term' => 'Kite App'])
        ->and(new MentionMatcher()->find('nothing here', ['Kite']))->toBeNull()
        ->and(new MentionMatcher()->find('Kite', ['', '  ']))->toBeNull();
});

it('links the mention on a line, keeping the original text as an alias', function (): void {
    $matcher = new MentionMatcher();
    $contents = "# Notes\r\nI love kite flying.\r\nKite again\n";

    expect($matcher->link($contents, 2, ['Kite'], 'Kite'))->toBe("# Notes\r\nI love [[kite]] flying.\r\nKite again\n")
        ->and($matcher->link($contents, 3, ['Kite', 'Kite App'], 'Kite App'))->toBe("# Notes\r\nI love kite flying.\r\n[[Kite App|Kite]] again\n")
        ->and($matcher->link('Last line kite', 1, ['kite'], 'Kite'))->toBe('Last line [[kite]]')
        ->and($matcher->link($contents, 1, ['Kite'], 'Kite'))->toBeNull()
        ->and($matcher->link($contents, 99, ['Kite'], 'Kite'))->toBeNull();
});

it('closes fences only with a matching marker', function (): void {
    $contents = "````\n```\nKite inside\n````\n~~~\nKite tilde\n~~~\nKite outside";

    expect(new MentionMatcher()->find($contents, ['Kite']))->toMatchArray(['line' => 8]);
});
