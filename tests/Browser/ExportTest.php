<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

it('exports a note as html and publishes it', function (): void {
    $vault = vaultWithFiles(['Guide.md' => "# Guide\n\nRead [[Setup]].", 'Setup.md' => '# Setup']);
    resolve(IndexVault::class)->handle($vault);
    $root = storage_path('framework/testing/published-'.uniqid());
    config(['kite.publish.folder' => $root]);
    $guide = Note::query()->where('path', 'Guide.md')->sole();

    visit(route('notes.show', [$vault, $guide]))
        ->click('@note-actions')
        ->assertMissing('@export-pdf')
        ->assertScript("document.querySelector('[data-test=export-html]') !== null", true)
        ->click('@publish-note')
        ->assertSee('Published “Guide” to')
        ->assertNoJavaScriptErrors();

    expect(File::exists($root.'/'.Str::slug($vault->name).'/guide/setup.html'))->toBeTrue();

    File::deleteDirectory($root);
});
