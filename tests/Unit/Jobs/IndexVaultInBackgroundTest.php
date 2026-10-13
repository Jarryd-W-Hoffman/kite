<?php

declare(strict_types=1);

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Jobs\IndexVaultInBackground;
use App\Services\IndexingProgress;

it('indexes notes and attachments, then clears progress', function (): void {
    $vault = vaultWithFiles(['A.md' => 'a', 'pic.png' => 'png']);
    $progress = resolve(IndexingProgress::class);

    $progress->start($vault);

    $job = new IndexVaultInBackground($vault);
    $job->handle(resolve(IndexVault::class), resolve(IndexAttachments::class), $progress);

    expect($vault->notes()->count())->toBe(1)
        ->and($vault->attachments()->count())->toBe(1)
        ->and($progress->get($vault))->toBeNull()
        ->and($job->uniqueId())->toBe((string) $vault->id)
        ->and($job->tries)->toBe(1);
});

it('clears progress when the job fails', function (): void {
    $vault = vaultWithFiles();
    $progress = resolve(IndexingProgress::class);

    $progress->start($vault);

    new IndexVaultInBackground($vault)->failed(new RuntimeException('boom'));

    expect($progress->get($vault))->toBeNull();
});
