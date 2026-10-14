<?php

declare(strict_types=1);

use App\Actions\IndexAttachments;
use App\Models\Attachment;
use Illuminate\Support\Facades\File;

it('indexes, updates and removes attachments', function (): void {
    $vault = vaultWithFiles([
        'Note.md' => 'not an attachment',
        'attachments/Photo.PNG' => 'png',
        'docs/Spec.pdf' => 'pdf',
        'script.exe' => 'nope',
    ]);

    $index = resolve(IndexAttachments::class);

    expect($index->handle($vault))->toBe(2)
        ->and($index->handle($vault))->toBe(0)
        ->and(Attachment::query()->orderBy('path')->get()->map->only(['path', 'path_key', 'name_key', 'extension', 'size'])->all())->toBe([
            ['path' => 'attachments/Photo.PNG', 'path_key' => 'attachments/photo.png', 'name_key' => 'photo.png', 'extension' => 'png', 'size' => 3],
            ['path' => 'docs/Spec.pdf', 'path_key' => 'docs/spec.pdf', 'name_key' => 'spec.pdf', 'extension' => 'pdf', 'size' => 3],
        ]);

    file_put_contents($vault->path.'/docs/Spec.pdf', 'bigger pdf');
    File::delete($vault->path.'/attachments/Photo.PNG');

    expect($index->handle($vault))->toBe(2)
        ->and(Attachment::query()->sole()->size)->toBe(10)
        ->and($vault->attachments()->count())->toBe(1);
});
