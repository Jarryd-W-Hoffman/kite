<?php

declare(strict_types=1);

use App\Actions\StoreAttachment;
use Illuminate\Http\UploadedFile;

it('stores uploads in the attachment folder with unique names', function (): void {
    $vault = vaultWithFiles(['attachments/Screen Shot.png' => 'taken']);
    $store = resolve(StoreAttachment::class);

    $first = $store->handle($vault, UploadedFile::fake()->createWithContent('Screen: Shot?.PNG', 'one'));
    $second = $store->handle($vault, UploadedFile::fake()->createWithContent('diagram.svg', '<svg/>'));

    expect($first)->toBe(['path' => 'attachments/Screen Shot 1.png', 'embed' => '![[Screen Shot 1.png]]'])
        ->and(file_get_contents($vault->path.'/attachments/Screen Shot 1.png'))->toBe('one')
        ->and($second)->toBe(['path' => 'attachments/diagram.svg', 'embed' => '![[diagram.svg]]'])
        ->and(glob($vault->path.'/attachments/.*.tmp'))->toBe([]);
});

it('uses the full path when the file name is not unique in the vault', function (): void {
    $vault = vaultWithFiles(['elsewhere/photo.png' => 'x']);

    $result = resolve(StoreAttachment::class)->handle($vault, UploadedFile::fake()->createWithContent('photo.png', 'y'));

    expect($result['embed'])->toBe('![[attachments/photo.png]]');
});

it('names untitled pastes by date and honours a custom folder', function (): void {
    config(['kite.attachments.folder' => '']);

    $vault = vaultWithFiles();

    $result = resolve(StoreAttachment::class)->handle($vault, UploadedFile::fake()->createWithContent('???.png', 'z'));

    expect($result['path'])->toBe('Pasted '.now()->format('Y-m-d His').'.png');
});
