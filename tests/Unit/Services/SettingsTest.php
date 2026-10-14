<?php

declare(strict_types=1);

use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('returns defaults from config and built-ins', function (): void {
    config(['kite.daily.folder' => 'Journal']);

    expect(resolve(Settings::class)->all())->toMatchArray([
        'editor_font' => 'mono',
        'editor_font_size' => 14,
        'daily_folder' => 'Journal',
        'ignored_folders' => ['node_modules'],
        'capture_shortcut' => 'CmdOrCtrl+Shift+Space',
    ])->not->toHaveKey('ignored_folders.*');
});

it('stores settings, trims strings and applies config-backed ones', function (): void {
    $settings = resolve(Settings::class);

    $settings->update([
        'editor_font' => 'serif',
        'daily_folder' => '  Journal  ',
        'ignored_folders' => ['node_modules', 'Archive'],
        'unknown' => 'ignored',
        'ignored_folders.*' => 'ignored',
    ]);

    expect($settings->all())->toMatchArray([
        'editor_font' => 'serif',
        'daily_folder' => 'Journal',
        'ignored_folders' => ['node_modules', 'Archive'],
    ])
        ->and(config('kite.daily.folder'))->toBe('Journal')
        ->and(config('kite.ignored_folders'))->toBe(['node_modules', 'Archive'])
        ->and(DB::table('settings')->count())->toBe(3);

    $settings->update(['editor_font' => 'sans']);

    expect($settings->all()['editor_font'])->toBe('sans')
        ->and(DB::table('settings')->count())->toBe(3);
});

it('re-applies stored settings to config', function (): void {
    DB::table('settings')->insert(['key' => 'daily_format', 'value' => json_encode('d-m-Y')]);
    config(['kite.daily.format' => 'Y-m-d']);

    resolve(Settings::class)->applyToConfig();

    expect(config('kite.daily.format'))->toBe('d-m-Y');
});

it('works before the settings table exists', function (): void {
    Schema::drop('settings');

    $settings = resolve(Settings::class);
    $settings->applyToConfig();

    expect($settings->all()['editor_font'])->toBe('mono');
});
