<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Vault;
use App\Services\AttachmentResolver;

it('prefers attachments next to the note, then the shallowest path', function (): void {
    $vault = Vault::factory()->create();

    foreach (['attachments/photo.png', 'Projects/photo.png', 'deep/a/b/photo.png', 'img/logo.svg', 'under_score/x.png'] as $path) {
        Attachment::factory()->for($vault)->create([
            'path' => $path,
            'path_key' => mb_strtolower($path),
            'name_key' => basename($path),
        ]);
    }

    $resolver = new AttachmentResolver();

    expect($resolver->resolve($vault, ['photo.png', 'Photo.PNG', 'b/photo.png', './img/logo.svg', 'under%score/x.png', 'missing.png', '  '], 'Projects'))->toBe([
        'photo.png' => 'Projects/photo.png',
        'Photo.PNG' => 'Projects/photo.png',
        'b/photo.png' => 'deep/a/b/photo.png',
        './img/logo.svg' => 'img/logo.svg',
    ])
        ->and($resolver->resolve($vault, ['photo.png']))->toBe(['photo.png' => 'attachments/photo.png'])
        ->and($resolver->resolve($vault, []))->toBe([]);
});
