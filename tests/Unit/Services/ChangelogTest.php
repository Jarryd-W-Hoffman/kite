<?php

declare(strict_types=1);

use App\Services\Changelog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->path = storage_path('framework/testing/changelog-'.Str::uuid().'.md');
    File::ensureDirectoryExists(dirname($this->path));
    File::put($this->path, <<<'MD'
        # Changelog

        ## [Unreleased]

        - Not out yet

        ## [0.2.0] - 2026-10-20

        ### Added

        - **A Welcome tab** <script>alert(1)</script>
        - [A link](javascript:alert(1)) and [another](https://example.com)

        ## [0.1.1] - 2026-10-17

        - A fix

        ## 0.1.0

        - The first release
        MD);
    config(['kite.changelog' => $this->path]);
});

afterEach(function (): void {
    File::delete($this->path);
});

it("finds a version's entry, without its heading or the next version", function (): void {
    expect(resolve(Changelog::class)->notes('0.2.0'))->toBe("### Added\n\n- **A Welcome tab** <script>alert(1)</script>\n- [A link](javascript:alert(1)) and [another](https://example.com)")
        ->and(resolve(Changelog::class)->notes('0.1.1'))->toBe('- A fix');
});

it('accepts a v prefix and headings without brackets or dates', function (): void {
    expect(resolve(Changelog::class)->notes('v0.1.1'))->toBe('- A fix')
        ->and(resolve(Changelog::class)->notes('0.1.0'))->toBe('- The first release');
});

it('has nothing for a version without an entry, or for Unreleased', function (): void {
    expect(resolve(Changelog::class)->notes('9.9.9'))->toBeNull()
        ->and(resolve(Changelog::class)->notes('Unreleased'))->toBeNull()
        ->and(resolve(Changelog::class)->html('9.9.9'))->toBeNull();
});

it('has nothing without a changelog file', function (): void {
    config(['kite.changelog' => storage_path('framework/testing/missing.md')]);

    expect(resolve(Changelog::class)->notes('0.2.0'))->toBeNull();
});

it('renders an entry with HTML escaped and unsafe links dropped', function (): void {
    $html = (string) resolve(Changelog::class)->html('0.2.0');

    expect($html)->toContain('<h3>Added</h3>')
        ->toContain('<strong>A Welcome tab</strong>')
        ->toContain('&lt;script&gt;')
        ->toContain('<a href="https://example.com">another</a>')
        ->not->toContain('<script>')
        ->not->toContain('javascript:');
});
