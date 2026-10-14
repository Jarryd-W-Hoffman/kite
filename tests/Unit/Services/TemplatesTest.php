<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Services\Templates;
use Carbon\CarbonImmutable;

it('fills in placeholders and leaves unknown ones alone', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-03-04 09:05'));

    $rendered = resolve(Templates::class)->render(
        "# {{title}}\n{{date}} {{ time }} | {{datetime}} | {{date:D j M}} | {{yesterday}} → {{tomorrow}} | {{folder}} in {{vault}} | {{unknown}} {{DATE}}",
        ['title' => 'Kickoff', 'folder' => 'Work', 'vault' => 'Notes'],
    );

    expect($rendered)->toBe("# Kickoff\n2026-03-04 09:05 | 2026-03-04 09:05 | Wed 4 Mar | 2026-03-03 → 2026-03-05 | Work in Notes | {{unknown}} 2026-03-04");
});

it('dates placeholders from the given date', function (): void {
    expect(resolve(Templates::class)->render('{{date}} {{date:Y}}', ['title' => 'x', 'date' => CarbonImmutable::parse('2020-02-29')]))
        ->toBe('2020-02-29 2020');
});

it('lists the notes in the templates folder', function (): void {
    $vault = vaultWithFiles([
        'Templates/Meeting.md' => '# {{title}}',
        'Templates/Work/Project.md' => '# {{title}}',
        'Templates.md' => '# Not a template',
        'Note.md' => '# Note',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $templates = resolve(Templates::class);

    expect(array_column($templates->all($vault), 'name'))->toBe(['Meeting', 'Work/Project'])
        ->and($templates->contains('Templates/Meeting.md'))->toBeTrue()
        ->and($templates->contains('Note.md'))->toBeFalse();

    config(['kite.templates.folder' => '']);

    expect($templates->all($vault))->toBe([])
        ->and($templates->contains('Templates/Meeting.md'))->toBeFalse();
});
