<?php

declare(strict_types=1);

use App\Services\VaultFilesystem;

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

    $files->delete($vault, 'Moved/New.md');

    expect($files->exists($vault, 'Moved/New.md'))->toBeFalse();
});
