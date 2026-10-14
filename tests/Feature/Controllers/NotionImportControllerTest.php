<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;

function notionUpload(array $files, string $name = 'Export.zip'): UploadedFile
{
    $path = (string) tempnam(sys_get_temp_dir(), 'notion-upload');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($files as $file => $contents) {
        $zip->addFromString($file, $contents);
    }

    $zip->close();

    return new UploadedFile($path, $name, 'application/zip', test: true);
}

it('imports a Notion export into a folder of the vault', function (): void {
    $vault = vaultWithFiles([]);

    $this->post(route('notion-imports.store', $vault), [
        'file' => notionUpload(['Home aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.md' => '# Home', 'Away aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.md' => '# Away']),
        'folder' => 'From Notion',
    ])
        ->assertRedirectToRoute('vaults.show', $vault)
        ->assertInertiaFlash('toast.message', 'Imported 2 notes into From Notion.');

    $this->post(route('notion-imports.store', $vault), ['file' => notionUpload(['Solo.md' => '# Solo'])])
        ->assertInertiaFlash('toast.message', 'Imported 1 note into Notion.');

    expect(file_get_contents($vault->path.'/From Notion/Home.md'))->toBe('# Home')
        ->and(file_exists($vault->path.'/Notion/Solo.md'))->toBeTrue();
});

it('explains when the upload is not a Notion export', function (): void {
    $vault = vaultWithFiles([]);
    $path = (string) tempnam(sys_get_temp_dir(), 'notion-upload');
    file_put_contents($path, 'not a zip');

    $this->post(route('notion-imports.store', $vault), ['file' => new UploadedFile($path, 'Export.zip', 'application/zip', test: true)])
        ->assertSessionHasErrors(['file' => 'That file is not a Notion export. Export as “Markdown & CSV” and choose the zip.']);

    $this->post(route('notion-imports.store', $vault), ['file' => UploadedFile::fake()->create('notes.txt', 1)])
        ->assertSessionHasErrors('file');
});
