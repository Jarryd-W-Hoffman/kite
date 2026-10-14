<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('rewrites the frontmatter of the text being edited without saving it', function (): void {
    $vault = vaultWithFiles(['Plan.md' => "---\nstatus: draft\n---\n# Plan"]);
    resolve(IndexVault::class)->handle($vault);

    $this->putJson(route('note-properties.update', [$vault, Note::query()->sole()]), [
        'contents' => "---\nstatus: draft\n---\n# Plan\n\nUnsaved line",
        'properties' => ['status' => 'active', 'due' => '2026-10-14', 'bad:key' => 'x', '' => 'y', 'count' => 3],
    ])
        ->assertOk()
        ->assertExactJson([
            'contents' => "---\nstatus: active\ndue: '2026-10-14'\ncount: 3\n---\n# Plan\n\nUnsaved line",
            'properties' => ['status' => 'active', 'due' => '2026-10-14', 'count' => 3],
        ]);

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("---\nstatus: draft\n---\n# Plan");
});

it('validates the request', function (): void {
    $vault = vaultWithFiles(['Plan.md' => '# Plan']);
    resolve(IndexVault::class)->handle($vault);

    $this->putJson(route('note-properties.update', [$vault, Note::query()->sole()]), ['properties' => 'nope'])
        ->assertJsonValidationErrors(['contents', 'properties']);
});
