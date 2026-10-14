<?php

declare(strict_types=1);

use App\Services\VaultFilesystem;
use Native\Desktop\Facades\Shell;

it('normalises safe relative paths', function (string $path, string $expected): void {
    expect(new VaultFilesystem()->normalise($path))->toBe($expected);
})->with([
    ['Note.md', 'Note.md'],
    ['./Folder//Sub/./Note.md', 'Folder/Sub/Note.md'],
    ['Folder\\Note.md', 'Folder/Note.md'],
]);

it('rejects paths that could escape the vault', function (string $path): void {
    new VaultFilesystem()->normalise($path);
})->with([
    '',
    '/etc/passwd',
    'C:\\Windows\\win.ini',
    '../outside.md',
    'Folder/../../outside.md',
    '.git/config',
    "Note\0.md",
])->throws(InvalidArgumentException::class);

it('lists, reads, writes, moves and deletes files inside the vault', function (): void {
    $vault = vaultWithFiles(['b.md' => 'B', 'A.MD' => 'A', 'c.txt' => 'C']);
    $files = new VaultFilesystem();

    $files->write($vault, 'Nested/New.md', 'Hello');
    $files->move($vault, 'Nested/New.md', 'Moved/New.md');

    expect(array_keys($files->markdownFiles($vault)))->toBe(['A.MD', 'b.md', 'Moved/New.md'])
        ->and($files->read($vault, 'Moved/New.md'))->toBe('Hello')
        ->and($files->stat($vault, 'Moved/New.md')['size'])->toBe(5)
        ->and($files->exists($vault, 'Nested/New.md'))->toBeFalse()
        ->and($files->absolutePath($vault, 'Moved/New.md'))->toBe($vault->path.'/Moved/New.md');

    $files->trash($vault, 'Moved/New.md');

    expect($files->exists($vault, 'Moved/New.md'))->toBeFalse();
});

it('lists files by extension and imports files atomically', function (): void {
    $vault = vaultWithFiles(['a.PNG' => 'a', 'b.pdf' => 'b', 'c.md' => 'c']);
    $files = new VaultFilesystem();

    $source = tempnam(sys_get_temp_dir(), 'kite');
    file_put_contents($source, 'imported');
    $files->import($vault, 'nested/d.png', $source);
    unlink($source);

    expect(array_keys($files->filesWithExtensions($vault, ['png', 'pdf'])))->toBe(['a.PNG', 'b.pdf', 'nested/d.png'])
        ->and($files->filesWithExtensions($vault, []))->toBe([])
        ->and($files->read($vault, 'nested/d.png'))->toBe('imported');
});

it('skips ignored folders when listing files', function (): void {
    config(['kite.ignored_folders' => ['Archive', ' /Private/Old/ ', '', 5]]);

    $vault = vaultWithFiles([
        'Keep.md' => 'x',
        'Archive/Gone.md' => 'x',
        'Private/Old/Gone.md' => 'x',
        'Private/New.md' => 'x',
    ]);

    expect(array_keys(new VaultFilesystem()->markdownFiles($vault)))->toBe(['Keep.md', 'Private/New.md'])
        ->and(new VaultFilesystem()->ignoredFolders())->toBe(['Archive', 'Private/Old']);

    config(['kite.ignored_folders' => 'nope']);

    expect(new VaultFilesystem()->ignoredFolders())->toBe([]);
});

it('lists every folder including empty ones, skipping dot and ignored folders', function (): void {
    config(['kite.ignored_folders' => ['node_modules', 'Archive/Old']]);

    $vault = vaultWithFiles(['b/Note.md' => '# Note', 'node_modules/pkg/x.md' => 'x', '.obsidian/app.json' => '{}']);

    $files = resolve(VaultFilesystem::class);
    $files->makeFolder($vault, 'A/Empty');
    $files->makeFolder($vault, 'Archive/Old/Deep');

    expect($files->folders($vault))->toBe(['A', 'A/Empty', 'Archive', 'b'])
        ->and($files->folderExists($vault, 'A/Empty'))->toBeTrue()
        ->and($files->folderExists($vault, 'b/Note.md'))->toBeFalse()
        ->and($files->isIgnored('node_modules'))->toBeTrue()
        ->and($files->isIgnored('src/node_modules/x'))->toBeTrue()
        ->and($files->isIgnored('Archive/Old/Deep'))->toBeTrue()
        ->and($files->isIgnored('Archive'))->toBeFalse();
});

it('trashes folders, or deletes them outside the desktop app', function (): void {
    $vault = vaultWithFiles(['Old/Note.md' => '# Note', 'Keep/Note.md' => '# Note']);
    $files = resolve(VaultFilesystem::class);

    $files->trash($vault, 'Old');

    expect(is_dir($vault->path.'/Old'))->toBeFalse();

    config(['nativephp-internal.running' => true]);
    Shell::fake();

    $files->trash($vault, 'Keep');

    Shell::assertTrashedFile($vault->path.'/Keep');
    expect(is_dir($vault->path.'/Keep'))->toBeTrue();
});

it('moves every remaining file out of a folder and removes it', function (): void {
    $vault = vaultWithFiles(['From/a.png' => 'png', 'From/.hidden' => 'h', 'From/deep/node_modules/x.js' => 'x']);
    mkdir($vault->path.'/From/empty');
    $files = resolve(VaultFilesystem::class);

    $files->moveFolderContents($vault, 'From', 'To/Nested');
    $files->moveFolderContents($vault, 'Missing', 'Anywhere');

    expect(is_dir($vault->path.'/From'))->toBeFalse()
        ->and(file_get_contents($vault->path.'/To/Nested/a.png'))->toBe('png')
        ->and(file_exists($vault->path.'/To/Nested/.hidden'))->toBeTrue()
        ->and(file_exists($vault->path.'/To/Nested/deep/node_modules/x.js'))->toBeTrue()
        ->and(is_dir($vault->path.'/To/Nested/empty'))->toBeTrue()
        ->and(is_dir($vault->path.'/Anywhere'))->toBeFalse();
});
