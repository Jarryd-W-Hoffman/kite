<?php

declare(strict_types=1);

use App\Services\AttachmentTypes;

it('reads attachment settings from config', function (): void {
    config([
        'kite.attachments.extensions' => ['PNG', 'pdf', 42],
        'kite.attachments.image_extensions' => ['png'],
        'kite.attachments.folder' => '/files/',
        'kite.attachments.max_upload_kilobytes' => 10,
    ]);

    $types = new AttachmentTypes();

    expect($types->extensions())->toBe(['png', 'pdf'])
        ->and($types->isAttachment('a/Photo.PNG'))->toBeTrue()
        ->and($types->isAttachment('Note.md'))->toBeFalse()
        ->and($types->isImage('x.png'))->toBeTrue()
        ->and($types->isImage('x.pdf'))->toBeFalse()
        ->and($types->folder())->toBe('files')
        ->and($types->maxUploadKilobytes())->toBe(10);
});

it('falls back to defaults for invalid config', function (): void {
    config([
        'kite.attachments.extensions' => 'png',
        'kite.attachments.folder' => null,
        'kite.attachments.max_upload_kilobytes' => 'lots',
    ]);

    $types = new AttachmentTypes();

    expect($types->extensions())->toBe([])
        ->and($types->folder())->toBe('attachments')
        ->and($types->maxUploadKilobytes())->toBe(51200);
});
