<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Services\Settings;

it('opens Welcome as a tab beside notes', function (): void {
    config(['nativephp.version' => '0.2.0']);
    $vault = vaultWithFiles(['Alpha.md' => '# Alpha', 'Beta.md' => '# Beta']);
    resolve(IndexVault::class)->handle($vault);
    Note::query()->where('path', 'Beta.md')->update(['opened_at' => now()]);

    visitSplit(route('welcome.show', [$vault, 'updated' => 1]))
        ->assertSeeIn('@whats-new', 'Kite has been updated to 0.2.0')
        ->click('[data-test=welcome-recent] a')
        ->assertSeeIn('@note-preview', 'Beta')
        ->assertSeeIn('@tab-bar', 'Welcome')
        ->assertSeeIn('@tab-bar', 'Beta')
        ->click('[role=tab] a[title=Welcome]')
        ->assertSee('Recent notes')
        ->assertAttribute('[role=tab][aria-selected=true] a', 'title', 'Welcome')
        ->click('#welcome_on_startup')
        ->assertNotChecked('#welcome_on_startup')
        ->wait(0.5)
        ->click('[aria-label="Close Welcome"]')
        ->assertSeeIn('@note-preview', 'Beta')
        ->assertNoJavaScriptErrors();

    expect(resolve(Settings::class)->all()['welcome_on_startup'])->toBeFalse();
});

it('shows the version and release notes link when not just updated', function (): void {
    config(['nativephp.version' => '0.2.0']);
    $vault = vaultWithFiles();

    visitSplit(route('welcome.show', $vault))
        ->assertSeeIn('@version', 'Kite 0.2.0')
        ->assertSee('Notes you open will show here.')
        ->assertMissing('@whats-new')
        ->assertNoJavaScriptErrors();
});

it("shows this version's changelog entry", function (): void {
    $path = storage_path('framework/testing/changelog-browser.md');
    file_put_contents($path, "## [0.2.0]\n\n### Added\n\n- **A Welcome tab** beside your notes\n");
    config(['nativephp.version' => '0.2.0', 'kite.changelog' => $path]);
    $vault = vaultWithFiles();

    visitSplit(route('welcome.show', [$vault, 'updated' => 1]))
        ->assertSeeIn('@whats-new', 'Kite has been updated to 0.2.0')
        ->assertSeeIn('@changelog-entry', 'A Welcome tab beside your notes')
        ->assertNoJavaScriptErrors();

    visitSplit(route('welcome.show', $vault))
        ->assertMissing('@whats-new')
        ->click('[data-test=whats-new-details] summary')
        ->assertSeeIn('@changelog-entry', 'A Welcome tab beside your notes')
        ->assertNoJavaScriptErrors();

    unlink($path);
});

it('asks once about crash reports, and remembers the answer', function (): void {
    config(['sentry.dsn' => 'https://public@sentry.example/1']);
    $vault = vaultWithFiles();

    visitSplit(route('welcome.show', $vault))
        ->assertSeeIn('@crash-reports-ask', 'Help make Kite better')
        ->click('Send crash reports')
        ->assertMissing('@crash-reports-ask')
        ->assertNoJavaScriptErrors();

    expect(resolve(Settings::class)->all()['crash_reports'])->toBeTrue();

    visitSplit(route('welcome.show', $vault))
        ->assertSee('Recent notes')
        ->assertMissing('@crash-reports-ask');
});

it("shows the quick capture shortcut set in Preferences, and no tip when it's off", function (): void {
    $vault = vaultWithFiles(['Alpha.md' => '# Alpha']);
    resolve(IndexVault::class)->handle($vault);
    resolve(Settings::class)->update(['capture_shortcut' => 'CommandOrControl+Alt+K']);

    $page = visit(route('welcome.show', $vault))
        ->assertSee('Quick capture from anywhere')
        ->assertScript("[...document.querySelectorAll('li')].find((tip) => tip.textContent.includes('Quick capture'))?.querySelector('kbd')?.textContent", 'Ctrl+Alt+K');

    resolve(Settings::class)->update(['capture_shortcut' => null]);

    $page->refresh()
        ->assertSee("Open today's note")
        ->assertDontSee('Quick capture from anywhere')
        ->assertNoJavaScriptErrors();
});
