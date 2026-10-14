<?php

declare(strict_types=1);

use App\Actions\DeleteFolder;
use App\Actions\DeleteNote;
use App\Actions\IndexVault;
use App\Actions\RenameFolder;
use App\Actions\RenameNote;
use App\Models\Note;
use App\Services\CommentStore;
use Native\Desktop\Facades\Shell;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['Work/Plan.md' => '# Plan', 'Work/Deep/Idea.md' => '# Idea', 'Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($this->vault);
    $this->store = resolve(CommentStore::class);
    $this->add = fn (string $path): array => $this->store->add($this->vault, $path, ['body' => 'b', 'quote' => 'q', 'prefix' => '', 'suffix' => '']);
    $this->exists = fn (string $path): bool => file_exists($this->vault->path.'/.kite/comments/'.$path.'.json');
});

it('keeps fields other tools added and skips entries it cannot use', function (): void {
    $file = $this->vault->path.'/.kite/comments/Home.md.json';
    @mkdir(dirname($file), recursive: true);
    file_put_contents($file, json_encode(['format' => 1, 'comments' => [
        ['id' => 'a', 'quote' => 'Home', 'body' => 'Hi', 'author' => 'Sam', 'resolved' => 'yes'],
        'garbage',
        ['id' => 'b'],
    ]]));

    expect($this->store->all($this->vault, 'Home.md'))->toBe([[
        'id' => 'a', 'body' => 'Hi', 'quote' => 'Home', 'prefix' => '', 'suffix' => '', 'resolved' => false, 'created_at' => '', 'updated_at' => '',
    ]]);

    $this->store->update($this->vault, 'Home.md', 'a', ['resolved' => true]);

    $saved = json_decode((string) file_get_contents($file), true)['comments'];

    expect($saved[0])->toMatchArray(['author' => 'Sam', 'resolved' => true])
        ->and($saved[1])->toBe('garbage')
        ->and($this->store->update($this->vault, 'Home.md', 'missing', ['body' => 'x']))->toBeNull()
        ->and($this->store->delete($this->vault, 'Home.md', 'missing'))->toBeFalse();
});

it('rejects comment files with the wrong shape', function (string $contents): void {
    $file = $this->vault->path.'/.kite/comments/Home.md.json';
    @mkdir(dirname($file), recursive: true);
    file_put_contents($file, $contents);

    $this->store->all($this->vault, 'Home.md');
})->with(['not json' => '{', 'no list' => '{"comments": {"a": 1}}', 'scalar' => '"hi"'])->throws(RuntimeException::class);

it('follows notes when kite renames them or their folder', function (): void {
    ($this->add)('Work/Plan.md');
    ($this->add)('Work/Deep/Idea.md');

    resolve(RenameNote::class)->handle(Note::query()->where('path', 'Work/Plan.md')->sole(), 'Work/Roadmap');

    expect(($this->exists)('Work/Roadmap.md'))->toBeTrue()
        ->and(($this->exists)('Work/Plan.md'))->toBeFalse();

    resolve(RenameFolder::class)->handle($this->vault, 'Work', 'Projects');

    expect(($this->exists)('Projects/Roadmap.md'))->toBeTrue()
        ->and(($this->exists)('Projects/Deep/Idea.md'))->toBeTrue()
        ->and(is_dir($this->vault->path.'/.kite/comments/Work'))->toBeFalse();
});

it('goes with notes and folders that are deleted', function (): void {
    ($this->add)('Home.md');
    ($this->add)('Work/Plan.md');

    resolve(DeleteNote::class)->handle(Note::query()->where('path', 'Home.md')->sole());

    expect(($this->exists)('Home.md'))->toBeFalse();

    resolve(DeleteFolder::class)->handle($this->vault, 'Work');

    expect(is_dir($this->vault->path.'/.kite'))->toBeFalse();
});

it('trashes comment files in the desktop app', function (): void {
    config(['nativephp-internal.running' => true]);
    Shell::fake();
    ($this->add)('Work/Plan.md');

    $this->store->trash($this->vault, 'Work', folder: true);
    $this->store->trash($this->vault, 'Nothing/Here.md');

    Shell::assertTrashedFile($this->vault->path.'/.kite/comments/Work');
});

it('is never indexed as notes', function (): void {
    ($this->add)('Home.md');
    file_put_contents($this->vault->path.'/.kite/comments/Stray.md', '# Stray');

    resolve(IndexVault::class)->handle($this->vault);

    expect(Note::query()->where('path', 'like', '.kite%')->count())->toBe(0);
});
