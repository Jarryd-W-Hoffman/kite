<?php

declare(strict_types=1);

use App\Services\Markdown\MarkdownParser;
use App\Services\Markdown\WikiLink;

function parseNote(string $contents, string $path = 'Notes/Example.md'): App\Services\Markdown\ParsedNote
{
    return new MarkdownParser()->parse($contents, $path);
}

it('parses yaml frontmatter and strips it from the body', function (): void {
    $note = parseNote(<<<'MD'
        ---
        title: Kite Roadmap
        created: 2026-01-02
        updated: 2026-01-02T10:30:00+00:00
        nested:
          when: 2026-03-04
        ---
        Body text.
        MD);

    expect($note->title)->toBe('Kite Roadmap')
        ->and($note->frontmatter)->toBe([
            'title' => 'Kite Roadmap',
            'created' => '2026-01-02',
            'updated' => '2026-01-02T10:30:00+00:00',
            'nested' => ['when' => '2026-03-04'],
        ])
        ->and($note->body)->toBe('Body text.');
});

it('ignores invalid or scalar frontmatter but still strips it', function (string $yaml): void {
    $note = parseNote("---\n{$yaml}\n---\nHello");

    expect($note->frontmatter)->toBe([])
        ->and($note->body)->toBe('Hello');
})->with([
    'invalid yaml' => 'title: [unclosed',
    'scalar yaml' => 'just a string',
]);

it('supports frontmatter closed at the end of the file and windows line endings', function (): void {
    expect(parseNote("---\r\ntitle: Only\r\n---")->frontmatter)->toBe(['title' => 'Only'])
        ->and(parseNote("\u{FEFF}---\ntitle: Bom\n...\nText")->title)->toBe('Bom');
});

it('tolerates invalid utf-8 bytes', function (): void {
    $note = parseNote("# Caf\xE9 notes\n[[Link]]");

    expect($note->title)->toBe('Caf? notes')
        ->and($note->links)->toHaveCount(1);
});

it('falls back to the first heading, then the file name, for the title', function (): void {
    expect(parseNote("Intro\n\n# The [[Big]] Idea ##\n\n# Second")->title)->toBe('The Big Idea')
        ->and(parseNote("```\n# Not a title\n```\nplain", 'Deep/Folder/My Note.md')->title)->toBe('My Note')
        ->and(parseNote("---\ntitle: '  '\n---\nplain", 'Blank.MD')->title)->toBe('Blank');
});

it('extracts wiki links with headings, aliases, embeds and line numbers', function (): void {
    $note = parseNote(<<<'MD'
        ---
        title: Links
        ---
        See [[Alpha]] and [[Beta#Setup|the beta setup]].
        ![[Folder/Gamma.md]]
        | table | [[Delta\|alias]] |
        [[  Spaced   Name  ]]
        MD);

    expect(array_map(fn (WikiLink $link): array => [
        $link->target, $link->targetKey, $link->heading, $link->alias, $link->isEmbed, $link->line,
    ], $note->links))->toBe([
        ['Alpha', 'alpha', null, null, false, 4],
        ['Beta', 'beta', 'Setup', 'the beta setup', false, 4],
        ['Folder/Gamma.md', 'folder/gamma', null, null, true, 5],
        ['Delta', 'delta', null, 'alias', false, 6],
        ['Spaced   Name', 'spaced name', null, null, false, 7],
    ]);
});

it('ignores links in code, attachments, empty targets and empty modifiers', function (): void {
    $note = parseNote(<<<'MD'
        ````md
        ```
        [[In Fence]]
        ```
        still fenced [[Still Fenced]] ~~~
        ````
        ~~~
        [[Tilde]]
        ~~~
        Inline `[[Code]]` and ``[[Double `tick`]]``.
        ![[photo.png]] [[#Local heading]] [[Doc.pdf]]
        [[Real#|]]
        MD);

    expect($note->links)->toHaveCount(1)
        ->and($note->links[0]->target)->toBe('Real')
        ->and($note->links[0]->heading)->toBeNull()
        ->and($note->links[0]->alias)->toBeNull();
});

it('collects frontmatter and inline tags, normalised and unique', function (): void {
    $note = parseNote(<<<'MD'
        ---
        tags: [Project, "#kite", 42, {nested: true}]
        ---
        # Heading is not a tag
        Working on #kite and #Ideas/Later, not #123 or a#b or [link](#anchor).
        `#code` [[Note#heading]] #project
        MD);

    expect($note->tags)->toBe(['42', 'ideas/later', 'kite', 'project']);
});

it('reads tags declared as a string or under the singular key', function (): void {
    expect(parseNote("---\ntags: alpha, beta gamma\n---\n")->tags)->toBe(['alpha', 'beta', 'gamma'])
        ->and(parseNote("---\ntag: solo\n---\n")->tags)->toBe(['solo'])
        ->and(parseNote("---\ntags: 7\n---\n")->tags)->toBe([]);
});

it('counts words outside of code', function (): void {
    expect(parseNote("Hello world, it's Kite.\n```\nignored words here\n```\nDone `skip`")->wordCount)->toBe(5);
});

it('normalises link keys', function (string $target, string $key): void {
    expect(WikiLink::keyFor($target))->toBe($key);
})->with([
    ['Note', 'note'],
    ['./Folder/Note.md', 'folder/note'],
    ['/Root\\Child.MD', 'root/child'],
    ['  Many   Spaces ', 'many spaces'],
]);
