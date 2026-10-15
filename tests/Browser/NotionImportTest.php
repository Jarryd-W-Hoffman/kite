<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

/*
 * The browser test server garbles binary uploads (bytes above 0x7F arrive as "?"), so a real zip
 * can't get through it; importing real exports is covered by the feature and unit tests.
 */
it('uploads a Notion export from the vault menu and shows problems with it', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    $upload = sys_get_temp_dir().'/Notion Export '.uniqid().'.zip';
    file_put_contents($upload, 'not really a zip');

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click($vault->name)
        ->click('@import-notion')
        ->assertVisible('@notion-import-dialog')
        ->assertDisabled('[data-test=notion-import-dialog] button[type=submit]')
        ->attach('file', $upload)
        ->click('Import')
        ->assertSeeIn('@notion-import-dialog', 'That file is not a Notion export.')
        ->assertNoJavaScriptErrors();

    expect(file_exists($vault->path.'/Notion'))->toBeFalse();
});
