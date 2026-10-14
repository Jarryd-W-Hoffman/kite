<?php

declare(strict_types=1);

use App\Actions\UpdateFrontmatter;

it('replaces the frontmatter and keeps the body', function (): void {
    expect(resolve(UpdateFrontmatter::class)->handle("---\nold: 1\n---\n# Body\n", ['status' => 'active', 'done' => true, 'due' => '2026-10-14', 'tags' => ['a', 'b'], 'empty' => null, 'none' => []]))
        ->toBe("---\nstatus: active\ndone: true\ndue: '2026-10-14'\ntags:\n  - a\n  - b\nempty:\nnone: []\n---\n# Body\n");
});

it('adds frontmatter to a note without any and removes it when emptied', function (): void {
    $action = resolve(UpdateFrontmatter::class);

    expect($action->handle("# Body\n", ['a' => 1]))->toBe("---\na: 1\n---\n# Body\n")
        ->and($action->handle("---\na: 1\n---\n\n# Body\n", []))->toBe("# Body\n")
        ->and($action->handle('', []))->toBe('');
});
