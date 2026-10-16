<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Non-markdown files inside a vault that Kite indexes, serves and embeds.
    | Uploaded files (pasted or dropped into the editor) are stored in the
    | folder below, relative to the vault root.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Shared State Cache Store
    |--------------------------------------------------------------------------
    |
    | Indexing progress, indexing locks and watcher state are shared between
    | the web server, the queue worker and the file watcher, so they need a
    | cache store visible across processes and never tied to an open
    | database transaction.
    |
    */

    'cache_store' => env('KITE_CACHE_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Ignored Folders
    |--------------------------------------------------------------------------
    |
    | Folder names (or vault-relative paths) that are never indexed or watched.
    | Dot-folders such as .git and .obsidian are always ignored.
    |
    */

    'ignored_folders' => ['node_modules'],

    /*
    |--------------------------------------------------------------------------
    | Indexing
    |--------------------------------------------------------------------------
    */

    'indexing' => [
        'batch_size' => (int) env('KITE_INDEXING_BATCH_SIZE', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily Notes & Quick Capture
    |--------------------------------------------------------------------------
    |
    | Daily notes live in the folder below, named with the PHP date format.
    | The optional template is a vault-relative markdown file; {{date}},
    | {{title}} and {{time}} placeholders are replaced when a note is made.
    | Quick capture appends to today's note from a global shortcut.
    |
    */

    'daily' => [
        'folder' => env('KITE_DAILY_FOLDER', 'Daily'),

        'format' => env('KITE_DAILY_FORMAT', 'Y-m-d'),

        'template' => env('KITE_DAILY_TEMPLATE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    | Every note in this vault-relative folder is offered by "New note from
    | template". Placeholders such as {{title}}, {{date}} and {{date:D j M}}
    | are filled in when a note is made from one.
    |
    */

    'templates' => [
        'folder' => env('KITE_TEMPLATES_FOLDER', 'Templates'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Publishing
    |--------------------------------------------------------------------------
    |
    | Published notes are written as small static sites under this absolute
    | folder, one subfolder per vault and note. Empty means "Kite Published"
    | in the home folder.
    |
    */

    'publish' => [
        'folder' => env('KITE_PUBLISH_FOLDER'),
    ],

    'capture' => [
        'shortcut' => env('KITE_CAPTURE_SHORTCUT', 'CmdOrCtrl+Shift+Space'),
    ],

    'attachments' => [
        'folder' => env('KITE_ATTACHMENTS_FOLDER', 'attachments'),

        'max_upload_kilobytes' => (int) env('KITE_MAX_UPLOAD_KILOBYTES', 51200),

        'image_extensions' => ['apng', 'avif', 'bmp', 'gif', 'jpeg', 'jpg', 'png', 'svg', 'webp'],

        'extensions' => [
            'apng', 'avif', 'bmp', 'gif', 'jpeg', 'jpg', 'png', 'svg', 'webp',
            'pdf',
            'flac', 'm4a', 'mp3', 'ogg', 'wav',
            'mov', 'mp4', 'webm',
            'csv', 'json', 'txt',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Locked notes
    |--------------------------------------------------------------------------
    |
    | How hard the vault passphrase is stretched (Argon2id) when a vault's lock
    | is set up. The cost is stored with the vault, so changing it only affects
    | vaults set up afterwards. Tests use the minimum to stay quick.
    |
    */

    'locks' => [
        'opslimit' => (int) env('KITE_LOCK_OPSLIMIT', SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE),
        'memlimit' => (int) env('KITE_LOCK_MEMLIMIT', SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development
    |--------------------------------------------------------------------------
    |
    | The processes `php artisan kite:dev` runs side by side, each stopped as
    | a whole process group when either ends.
    |
    */

    'dev' => [
        'commands' => [
            'app' => ['php', 'artisan', 'native:run', '--no-interaction'],
            'vite' => ['bun', 'run', 'dev'],
        ],

        // Ubuntu 24.04 and later block the user namespaces Chromium's sandbox uses, and the setuid
        // helper npm installs isn't set up, so Electron aborts at start. Development only: builds
        // keep the sandbox.
        'env' => PHP_OS_FAMILY === 'Linux' ? ['ELECTRON_DISABLE_SANDBOX' => '1'] : [],
    ],

];
