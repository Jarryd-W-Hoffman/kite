<?php

declare(strict_types=1);

use App\Models\Vault;
use App\Services\IndexingProgress;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;

it('tracks progress per vault', function (): void {
    $first = Vault::factory()->create();
    $second = Vault::factory()->create();
    $progress = new IndexingProgress();

    expect($progress->get($first))->toBeNull();

    $progress->start($first);
    $progress->update($second, 3, 9);

    expect($progress->get($first))->toBe(['done' => 0, 'total' => 0])
        ->and($progress->get($second))->toBe(['done' => 3, 'total' => 9]);

    $progress->finish($first);

    expect($progress->get($first))->toBeNull();
});

it('ignores malformed cached progress', function (): void {
    $vault = Vault::factory()->create();

    Cache::store('array')->put("kite:indexing:{$vault->id}", ['done' => 'x'], 60);

    expect(new IndexingProgress()->get($vault))->toBeNull();
});

it('provides a per vault lock', function (): void {
    $vault = Vault::factory()->create();
    $progress = new IndexingProgress();

    $lock = $progress->lock($vault);

    expect($lock->get())->toBeTrue()
        ->and($progress->lock($vault)->get())->toBeFalse();

    $lock->release();

    expect($progress->lock($vault)->get())->toBeTrue();
});

it('requires a cache store that supports locks', function (): void {
    config(['cache.stores.nolocks' => ['driver' => 'null'], 'kite.indexing.cache_store' => 'nolocks']);
    Cache::extend('null', fn () => Cache::repository(new class implements Store
    {
        public function get($key): mixed
        {
            return null;
        }

        public function many(array $keys): array
        {
            return [];
        }

        public function put($key, $value, $seconds): bool
        {
            return true;
        }

        public function putMany(array $values, $seconds): bool
        {
            return true;
        }

        public function increment($key, $value = 1): bool
        {
            return false;
        }

        public function decrement($key, $value = 1): bool
        {
            return false;
        }

        public function forever($key, $value): bool
        {
            return true;
        }

        public function touch($key, $seconds): bool
        {
            return true;
        }

        public function forget($key): bool
        {
            return true;
        }

        public function flush(): bool
        {
            return true;
        }

        public function getPrefix(): string
        {
            return '';
        }
    }));

    new IndexingProgress()->lock(Vault::factory()->create());
})->throws(RuntimeException::class, 'atomic locks');
