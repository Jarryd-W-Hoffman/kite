<?php

declare(strict_types=1);

use App\Services\SystemOpener;
use Native\Desktop\Facades\Shell;

it("opens files on Linux as file URLs, which Electron doesn't wait on", function (): void {
    Shell::fake();

    new SystemOpener('Linux')->open('/home/me/My notes/attachments/Café #1.png');

    Shell::assertOpenedExternal('file:///home/me/My%20notes/attachments/Caf%C3%A9%20%231.png');
});

it('opens files directly on macOS and Windows', function (string $os, string $path): void {
    Shell::fake();

    new SystemOpener($os)->open($path);

    Shell::assertOpenedFile($path);
})->with([
    'macOS' => ['Darwin', '/Users/me/notes/attachments/a.png'],
    'Windows' => ['Windows', 'C:\\Users\\me\\notes\\attachments\\a.png'],
]);
