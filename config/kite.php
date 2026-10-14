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
    | Indexing
    |--------------------------------------------------------------------------
    */

    'indexing' => [
        'batch_size' => (int) env('KITE_INDEXING_BATCH_SIZE', 200),
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
