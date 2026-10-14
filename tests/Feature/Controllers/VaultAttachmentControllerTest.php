<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;

it('uploads an attachment into the vault', function (): void {
    $vault = vaultWithFiles();

    $this->post(route('vault-attachments.store', $vault), [
        'file' => UploadedFile::fake()->image('diagram.png'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertExactJson(['path' => 'attachments/diagram.png', 'embed' => '![[diagram.png]]']);

    expect(file_exists($vault->path.'/attachments/diagram.png'))->toBeTrue()
        ->and($vault->attachments()->count())->toBe(1);
});

it('validates uploads', function (?UploadedFile $file): void {
    config(['kite.attachments.max_upload_kilobytes' => 1]);

    $this->post(route('vault-attachments.store', vaultWithFiles()), array_filter(['file' => $file]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
})->with([
    'missing' => [null],
    'disallowed type' => [UploadedFile::fake()->create('virus.exe', 1)],
    'too large' => [UploadedFile::fake()->create('big.pdf', 5)],
]);
