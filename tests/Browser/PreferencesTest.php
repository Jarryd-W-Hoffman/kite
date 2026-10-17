<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Services\Settings;

it('edits preferences in a modal and applies them to the open note', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->click('@preferences-link')
        ->assertVisible('@preferences-dialog')
        ->assertValue('editor_font', 'sans')
        ->select('editor_font', 'serif')
        ->fill('editor_font_size', '18')
        ->fill('ignored_folders', "node_modules\nArchive")
        ->click('Save preferences')
        ->assertSee('Preferences saved.')
        ->assertMissing('@preferences-dialog')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=note-editor]')).fontSize", '18px')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=note-editor]')).fontFamily.includes('Georgia')", true)
        ->click('@preferences-link')
        ->assertValue('ignored_folders', "node_modules\nArchive")
        ->assertNoJavaScriptErrors();

    expect(config('kite.ignored_folders'))->toBe(['node_modules', 'Archive']);
});

it('opens preferences from the keyboard and closes without saving', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->keys('@note-editor', 'Control+,')
        ->assertVisible('@preferences-dialog')
        ->fill('daily_folder', 'Unsaved')
        ->click('Cancel')
        ->assertMissing('@preferences-dialog')
        ->assertNoJavaScriptErrors();

    expect(config('kite.daily.folder'))->toBe('Daily');
});

it('switches the theme from preferences, the sidebar and the keyboard', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));
    $isDark = "document.documentElement.classList.contains('dark')";

    $page->click('@preferences-link')
        ->click('@appearance-dark')
        ->assertScript($isDark, true)
        ->assertAttribute('@appearance-dark', 'aria-checked', 'true')
        ->click('Cancel')
        ->assertAttribute('@theme-toggle', 'data-appearance', 'dark')
        ->click('@theme-toggle')
        ->assertAttribute('@theme-toggle', 'data-appearance', 'system')
        ->click('@theme-toggle')
        ->assertAttribute('@theme-toggle', 'data-appearance', 'light')
        ->assertScript($isDark, false)
        ->keys('@note-editor', 'Control+Shift+L')
        ->assertScript($isDark, true)
        ->assertAttribute('@theme-toggle', 'data-appearance', 'dark')
        ->assertNoJavaScriptErrors();

    expect(resolve(Settings::class)->all()['appearance'])->toBe('dark');
});

it('collects a diagnostics report to copy from preferences', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@preferences-link')
        ->click('@copy-diagnostics')
        ->assertSeeIn('@diagnostics-report', 'Kite diagnostics')
        ->assertSeeIn('@diagnostics-report', '1 notes')
        ->assertDontSee('Show log files')
        ->assertNoJavaScriptErrors();
});

it('jumps to a section without moving the dialog', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@preferences-link')
        ->assertVisible('@preferences-dialog')
        ->assertValue('editor_font', 'sans')
        ->click('nav[aria-label="Preference sections"] button:nth-child(9)')
        ->assertAttribute('nav[aria-label="Preference sections"] button:nth-child(9)', 'aria-current', 'true')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-test=preferences-dialog]').scrollTop", 0)
        ->assertScript("document.querySelector('[data-test=preference-sections]').scrollTop > 0", true)
        // Near the end the list can't scroll Welcome to the top, but it must be in view.
        ->assertScript("(() => { const list = document.querySelector('[data-test=preference-sections]').getBoundingClientRect(); const section = document.getElementById('preferences-welcome').getBoundingClientRect(); return section.top >= list.top && section.top < list.bottom - 40; })()", true)
        ->assertNoJavaScriptErrors();
});

it('highlights the section that was chosen or scrolled to', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@preferences-link')
        ->assertValue('editor_font', 'sans');

    $sections = ['Appearance', 'Editor', 'Vault files', 'Daily notes', 'Templates', 'Publishing', 'File tree', 'Quick capture', 'Welcome', 'Diagnostics'];

    foreach ([...$sections, 'Editor', 'File tree'] as $title) {
        $index = array_search($title, $sections, true) + 1;

        $page->click("nav[aria-label=\"Preference sections\"] button:nth-child({$index})")
            ->wait(0.9)
            ->assertSeeIn('nav[aria-label="Preference sections"] [aria-current=true]', $title);
    }

    // Scrolling by hand: the section at the top, then the last one at the very end.
    $page->script("const list = document.querySelector('[data-test=preference-sections]'); list.scrollTop += document.getElementById('preferences-vault-files').getBoundingClientRect().top - list.getBoundingClientRect().top - 24;");
    $page->wait(0.3)
        ->assertSeeIn('nav[aria-label="Preference sections"] [aria-current=true]', 'Vault files');

    $page->script("const list = document.querySelector('[data-test=preference-sections]'); list.scrollTop = list.scrollHeight;");
    $page->wait(0.3)
        ->assertSeeIn('nav[aria-label="Preference sections"] [aria-current=true]', 'Diagnostics')
        ->assertNoJavaScriptErrors();
});
