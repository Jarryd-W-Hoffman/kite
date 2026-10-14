<?php

declare(strict_types=1);

use App\Services\KiteCache;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;

it('uses the configured store and falls back to the default store', function (): void {
    config(['kite.cache_store' => 'array']);

    expect(new KiteCache()->store()->getStore())->toBeInstanceOf(ArrayStore::class);

    config(['kite.cache_store' => null, 'cache.default' => 'file']);

    expect(new KiteCache()->store()->getStore())->toBeInstanceOf(FileStore::class);
});
