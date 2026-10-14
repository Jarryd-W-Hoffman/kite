<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\PublishNote;
use App\Models\Note;
use Illuminate\Support\Facades\File;
use Native\Desktop\Facades\Shell;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Guide.md' => "# Guide\n\nRead [[Setup]], [[Index]] and [[Missing]].",
        'Setup.md' => "# Setup\n\nBack to [[Guide]]. Also [[Other]].",
        'Index.md' => '# Index',
        'Other.md' => '# Other',
    ]);
    $this->vault->update(['name' => 'My Notes']);

    resolve(IndexVault::class)->handle($this->vault);
    $this->root = storage_path('framework/testing/published-'.uniqid());
    config(['kite.publish.folder' => $this->root]);
    $this->guide = Note::query()->where('path', 'Guide.md')->sole();
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

it('publishes a note and the notes it links to as linked pages', function (): void {
    $folder = $this->root.'/my-notes/guide';
    File::ensureDirectoryExists($folder);
    File::put($folder.'/stale.html', 'old');
    File::put($folder.'/keep.txt', 'mine');

    $this->fromRoute('notes.show', [$this->vault, $this->guide])
        ->post(route('note-publications.store', [$this->vault, $this->guide]))
        ->assertRedirectToRoute('notes.show', [$this->vault, $this->guide])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "Published “Guide” to {$folder}"]);

    expect(collect(File::files($folder))->map->getFilename()->sort()->values()->all())->toBe(['index-2.html', 'index.html', 'keep.txt', 'setup.html'])
        ->and(File::get($folder.'/index.html'))->toContain('href="setup.html"')->toContain('href="index-2.html"')->toContain('<span class="wiki-link">Missing</span>')
        ->and(File::get($folder.'/setup.html'))->toContain('<a href="index.html">← Back</a>')->toContain('<span class="wiki-link">Other</span>');
});

it('shows the published page in the desktop app', function (): void {
    config(['nativephp-internal.running' => true]);
    Shell::fake();

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->post(route('note-publications.store', [$this->vault, $this->guide]))
        ->assertRedirect();

    Shell::assertShowInFolder($this->root.'/my-notes/guide/index.html');
});

it('defaults to a folder in the home directory', function (): void {
    config(['kite.publish.folder' => null]);

    expect(resolve(PublishNote::class)->root())->toEndWith(DIRECTORY_SEPARATOR.'Kite Published');
});
