<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->path = storage_path('framework/testing/changelog-'.Str::uuid().'.md');
    File::ensureDirectoryExists(dirname($this->path));
    File::put($this->path, "# Changelog\n\n## [0.2.0] - 2026-10-20\n\n### Added\n\n- A Welcome tab\n");
    config(['kite.changelog' => $this->path]);
});

afterEach(function (): void {
    File::delete($this->path);
});

it("prints a version's entry, blank lines included", function (): void {
    expect(Artisan::call('kite:release-notes', ['version' => 'v0.2.0']))->toBe(0)
        ->and(Artisan::output())->toBe("### Added\n\n- A Welcome tab".PHP_EOL);
});

it('fails for a version without an entry, so it cannot be released', function (): void {
    $this->artisan('kite:release-notes', ['version' => 'v0.3.0'])
        ->expectsOutputToContain('CHANGELOG.md has no entry for v0.3.0')
        ->assertExitCode(1);
});

it('has an entry for every released version in the real changelog', function (): void {
    config(['kite.changelog' => base_path('CHANGELOG.md')]);

    foreach (['0.1.0', '0.1.1'] as $version) {
        $this->artisan('kite:release-notes', ['version' => $version])->assertExitCode(0);
    }
});
