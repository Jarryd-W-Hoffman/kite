<?php

declare(strict_types=1);

use App\Actions\FindNotesWithTag;
use App\Actions\IndexVault;

it('finds notes with a tag or nested tags, newest first', function (): void {
    $vault = vaultWithFiles([
        'Old.md' => '#project',
        'Child.md' => '#project/kite #idea',
        'Other.md' => '#projects #pro_ject',
    ]);
    touch($vault->path.'/Old.md', time() - 100);
    resolve(IndexVault::class)->handle($vault);

    $notes = resolve(FindNotesWithTag::class)->handle($vault, '#Project/');

    expect(array_column($notes, 'path'))->toBe(['Child.md', 'Old.md'])
        ->and($notes[0]['tags'])->toBe(['idea', 'project/kite'])
        ->and(resolve(FindNotesWithTag::class)->handle($vault, 'pro_ject'))->toHaveCount(1)
        ->and(resolve(FindNotesWithTag::class)->handle($vault, ' # '))->toBe([]);
});
