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
    | Indexing
    |--------------------------------------------------------------------------
    |
    | Progress and locks are shared between the queue worker and the app, so
    | they need a cache store that is visible across processes and not tied
    | to an open database transaction.
    |
    */

    'indexing' => [
        'cache_store' => env('KITE_INDEXING_CACHE_STORE', 'file'),

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
