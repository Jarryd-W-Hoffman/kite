<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Models\Vault;
use App\Services\IndexingProgress;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

#[Timeout(3600)]
#[Tries(1)]
final class IndexVaultInBackground implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Vault $vault)
    {
        //
    }

    public function uniqueId(): string
    {
        return (string) $this->vault->id;
    }

    public function handle(IndexVault $indexVault, IndexAttachments $indexAttachments, IndexingProgress $progress): void
    {
        try {
            $indexVault->handle($this->vault, fn (int $done, int $total) => $progress->update($this->vault, $done, $total));
            $indexAttachments->handle($this->vault);
        } finally {
            $progress->finish($this->vault);
        }
    }

    public function failed(?Throwable $exception): void
    {
        resolve(IndexingProgress::class)->finish($this->vault);
    }
}
