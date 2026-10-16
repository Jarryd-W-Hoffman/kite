<?php

declare(strict_types=1);

it('keeps development settings out of desktop builds', function (): void {
    expect(config('nativephp.cleanup_env_keys'))->toContain('APP_ENV', 'APP_DEBUG')
        ->and(config('nativephp.cleanup_exclude_files'))->toContain('public/hot')
        ->and(config('nativephp.prebuild'))->toBe(['bun run build']);
});

it('describes the app as Kite rather than with NativePHP defaults', function (): void {
    expect(config('nativephp.app_id'))->toBe('com.kite.app')
        ->and(config('nativephp.author'))->toBe('Jarryd Hoffman')
        ->and(config('nativephp.copyright'))->toBe('Copyright © 2026 Jarryd Hoffman')
        ->and(config('nativephp.website'))->toBe('https://github.com/Jarryd-W-Hoffman/kite');
});
