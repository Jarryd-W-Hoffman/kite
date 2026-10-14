<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('edits frontmatter as typed properties', function (): void {
    $vault = vaultWithFiles(['Plan.md' => "---\nstatus: draft\ndone: false\ndue: 2026-10-14\ntags: [work, urgent]\n---\n# Plan\n\nBody"]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->assertAttribute('[data-test=property][data-name=status]', 'data-type', 'text')
        ->assertAttribute('[data-test=property][data-name=done]', 'data-type', 'checkbox')
        ->assertAttribute('[data-test=property][data-name=due]', 'data-type', 'date')
        ->assertAttribute('[data-test=property][data-name=tags]', 'data-type', 'list')
        ->fill('[aria-label="Property value status"]', 'active')
        ->keys('[aria-label="Property value status"]', 'Enter')
        ->wait(0.5)
        ->click('[aria-label="Property value done"]')
        ->wait(0.5)
        ->click('[aria-label="Remove urgent"]')
        ->wait(0.5)
        ->click('@add-property')
        ->fill('[aria-label="New property name"]', 'priority')
        ->select('[aria-label="New property type"]', 'number')
        ->click('[data-test=new-property] button[type=submit]')
        ->wait(1.5)
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))
        ->toBe("---\nstatus: active\ndone: true\ndue: '2026-10-14'\ntags:\n  - work\npriority: 0\n---\n# Plan\n\nBody");
});
