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

];
