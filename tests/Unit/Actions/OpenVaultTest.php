<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use App\Jobs\IndexVaultInBackground;
use App\Services\IndexingProgress;
use App\Actions\OpenVault;
use App\Models\Vault;

it('registers a folder as a vault and indexes it', function (): void {
    $folder = vaultWithFiles(['Note.md' => '# Hello']);
    $folder->delete();

    $vault = resolve(OpenVault::class)->handle($folder->path.'/');

    expect($vault->path)->toBe($folder->path)
        ->and($vault->name)->toBe(basename($folder->path))
        ->and($vault->opened_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($vault->notes()->count())->toBe(1);
});

it('re-opens an existing vault instead of duplicating it', function (): void {
    $existing = vaultWithFiles();

    $this->travel(5)->minutes();

    $vault = resolve(OpenVault::class)->handle($existing->path);

    expect($vault->id)->toBe($existing->id)
        ->and(Vault::query()->count())->toBe(1)
        ->and($vault->opened_at?->toIso8601String())->toBe(now()->toIso8601String());
});

it('rejects folders that do not exist', function (): void {
    resolve(OpenVault::class)->handle('/definitely/not/a/kite/folder');
})->throws(InvalidArgumentException::class, 'does not exist');

it('rejects files', function (): void {
    $vault = vaultWithFiles(['Note.md' => 'x']);

    resolve(OpenVault::class)->handle($vault->path.'/Note.md');
})->throws(InvalidArgumentException::class, 'does not exist');

it('queues indexing in the background and marks the vault as indexing', function (): void {
    Queue::fake();

    $folder = vaultWithFiles(['Note.md' => '# Hello']);
    $folder->delete();

    $vault = resolve(OpenVault::class)->handle($folder->path, inBackground: true);

    Queue::assertPushed(IndexVaultInBackground::class, fn ($job): bool => $job->vault->is($vault));

    expect($vault->notes()->count())->toBe(0)
        ->and(resolve(IndexingProgress::class)->get($vault))->toBe(['done' => 0, 'total' => 0]);
});
