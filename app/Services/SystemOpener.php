<?php

declare(strict_types=1);

namespace App\Services;

use Native\Desktop\Facades\Shell;

/**
 * Opens a file or folder in the app the system uses for it (an image viewer, a PDF reader, the
 * file manager), in the desktop app.
 */
final readonly class SystemOpener
{
    public function __construct(private string $os = PHP_OS_FAMILY)
    {
        //
    }

    public function open(string $absolutePath): void
    {
        // On Linux, Electron's shell.openPath never reports back once it has started xdg-open, so
        // NativePHP's request (and with it the PHP server, which handles one request at a time)
        // would wait forever and nothing else in Kite would load. openExternal reports back as
        // soon as the app has started, and xdg-open opens a file:// URL just the same.
        if ($this->os === 'Linux') {
            Shell::openExternal($this->fileUrl($absolutePath));

            return;
        }

        Shell::openFile($absolutePath);
    }

    private function fileUrl(string $absolutePath): string
    {
        return 'file://'.implode('/', array_map(rawurlencode(...), explode('/', $absolutePath)));
    }
}
