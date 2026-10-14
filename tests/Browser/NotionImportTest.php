<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('imports a Notion export from the vault menu', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    $zipPath = sys_get_temp_dir().'/Notion Export '.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('Roadmap aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.md', '# Roadmap');
    $zip->close();

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click($vault->name)
        ->click('@import-notion')
        ->assertVisible('@notion-import-dialog')
        ->attach('file', $zipPath)
        ->click('Import')
        ->assertMissing('@notion-import-dialog')
        ->assertSee('Imported 1 note into Notion.')
        ->assertSee('Roadmap')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Notion/Roadmap.md'))->toBe('# Roadmap');
});
