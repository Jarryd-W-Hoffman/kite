<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\LockNote;
use App\Actions\RenameNote;
use App\Models\Note;
use App\Services\Canvases;
use App\Services\NoteLocks;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Ideas.md' => "# Ideas\n\nA **bold** idea.",
        'Secret.md' => '# Secret',
        'attachments/photo.png' => 'png',
        'attachments/report.pdf' => 'pdf',
    ]);
    resolve(IndexVault::class)->handle($this->vault);
    $this->canvases = resolve(Canvases::class);
    $this->file = fn (string $path): array => json_decode((string) file_get_contents($this->vault->path.'/'.$path), true);
});

function canvasNode(string $id, array $fields = []): array
{
    return ['id' => $id, 'type' => 'text', 'text' => $id, 'x' => 0, 'y' => 0, 'width' => 250, 'height' => 60, ...$fields];
}

it('creates canvases with free names, in a folder if asked', function (): void {
    $this->post(route('canvases.store', $this->vault))->assertRedirectToRoute('canvases.show', [$this->vault, 'Untitled.canvas']);
    $this->post(route('canvases.store', $this->vault))->assertRedirectToRoute('canvases.show', [$this->vault, 'Untitled 2.canvas']);
    $this->post(route('canvases.store', $this->vault), ['name' => 'Plan / Board', 'folder' => 'Boards'])
        ->assertRedirectToRoute('canvases.show', [$this->vault, 'Boards/Plan   Board.canvas']);
    $this->post(route('canvases.store', $this->vault), ['folder' => '../outside'])->assertSessionHasErrors('folder');

    expect(file_get_contents($this->vault->path.'/Untitled.canvas'))->toBe("{\n\t\"nodes\": [],\n\t\"edges\": []\n}")
        ->and(array_column($this->canvases->all($this->vault), 'name'))->toBe(['Plan   Board', 'Untitled', 'Untitled 2']);
});

it('shows a canvas with what its file cards point at', function (): void {
    $note = Note::query()->where('path', 'Ideas.md')->sole();
    resolve(LockNote::class)->handle(Note::query()->where('path', 'Secret.md')->sole(), resolve(NoteLocks::class)->setUp($this->vault, 'correct horse'));
    $this->canvases->write($this->vault, 'Board.canvas', [
        canvasNode('a', ['color' => '2', 'custom' => 'kept']),
        canvasNode('n', ['type' => 'file', 'file' => 'Ideas.md', 'text' => null]),
        canvasNode('s', ['type' => 'file', 'file' => 'Secret.md']),
        canvasNode('i', ['type' => 'file', 'file' => 'attachments/photo.png']),
        canvasNode('p', ['type' => 'file', 'file' => 'attachments/report.pdf']),
        canvasNode('m', ['type' => 'file', 'file' => 'Gone.md']),
        canvasNode('x', ['type' => 'file', 'file' => '../../etc/passwd']),
        canvasNode('y', ['type' => 'file', 'file' => 'attachments/../../outside.png']),
    ], [['id' => 'e', 'fromNode' => 'a', 'toNode' => 'n']], ['version' => '1.0']);

    $this->get(route('canvases.show', [$this->vault, 'Board.canvas']))
        ->assertInertia(fn ($page) => $page
            ->component('canvas/show')
            ->where('canvas.name', 'Board')
            ->where('canvas.readable', true)
            ->where('canvas.hash', $this->canvases->read($this->vault, 'Board.canvas')['hash'])
            ->where('canvas.nodes.0.custom', 'kept')
            ->where('texts.a', fn (string $html): bool => str_contains($html, '<p>a</p>'))
            ->where('canvas.edges.0.toNode', 'n')
            ->where('files', function (Collection $files) use ($note): bool {
                $files = $files->toArray();

                expect($files['Ideas.md'])->toMatchArray(['kind' => 'note', 'note_id' => $note->id, 'title' => 'Ideas', 'locked' => false])
                    ->and($files['Ideas.md']['html'])->toContain('<strong>bold</strong>')
                    ->and($files['Secret.md'])->toMatchArray(['kind' => 'note', 'locked' => true, 'html' => ''])
                    ->and($files['attachments/photo.png'])->toBe(['kind' => 'image', 'url' => route('vault-files.show', [$this->vault, 'attachments/photo.png'], false)])
                    ->and($files['attachments/report.pdf']['kind'])->toBe('file')
                    ->and($files['Gone.md'])->toBe(['kind' => 'missing'])
                    ->and($files['../../etc/passwd'])->toBe(['kind' => 'missing'])
                    ->and($files['attachments/../../outside.png'])->toBe(['kind' => 'missing']);

                return true;
            })
            ->has('canvases', 1));
});

it('shows a canvas that can’t be read without offering to overwrite it', function (string $contents): void {
    file_put_contents($this->vault->path.'/Broken.canvas', $contents);

    $this->get(route('canvases.show', [$this->vault, 'Broken.canvas']))
        ->assertInertia(fn ($page) => $page->where('canvas.readable', false)->where('canvas.hash', null)->where('canvas.nodes', []));

    $this->putJson(route('canvases.update', [$this->vault, 'Broken.canvas']), ['base_hash' => null, 'nodes' => [], 'edges' => []])
        ->assertConflict()
        ->assertJsonPath('message', 'This canvas can’t be read, so it wasn’t saved.');

    expect(file_get_contents($this->vault->path.'/Broken.canvas'))->toBe($contents);
})->with(['not json' => ['{nodes'], 'a list' => ['[1, 2]'], 'a string' => ['"canvas"']]);

it('reads empty files and missing lists as an empty canvas', function (): void {
    file_put_contents($this->vault->path.'/Empty.canvas', '');
    file_put_contents($this->vault->path.'/Bare.canvas', '{"nodes": "nope", "edges": [1, {"id": "e", "fromNode": "a", "toNode": "b"}]}');

    expect($this->canvases->read($this->vault, 'Empty.canvas'))->toMatchArray(['nodes' => [], 'edges' => [], 'extra' => []])
        ->and($this->canvases->read($this->vault, 'Bare.canvas')['nodes'])->toBe([])
        ->and($this->canvases->read($this->vault, 'Bare.canvas')['edges'])->toHaveCount(1);
});

it('saves a canvas, keeping fields it doesn’t know, unless the file changed meanwhile', function (): void {
    $this->canvases->write($this->vault, 'Board.canvas', [], [], ['version' => '1.0']);
    $hash = $this->canvases->read($this->vault, 'Board.canvas')['hash'];
    $nodes = [canvasNode('a', ['custom' => ['deep' => true]]), canvasNode('b', ['type' => 'link', 'url' => 'https://example.com', 'text' => null])];
    $edges = [['id' => 'e', 'fromNode' => 'a', 'toNode' => 'b', 'toEnd' => 'arrow', 'label' => 'leads to']];

    $saved = $this->putJson(route('canvases.update', [$this->vault, 'Board.canvas']), ['base_hash' => $hash, 'nodes' => $nodes, 'edges' => $edges])
        ->assertOk()
        ->json('hash');

    expect(($this->file)('Board.canvas'))->toBe(['version' => '1.0', 'nodes' => [...array_slice($nodes, 0, 1), [...$nodes[1], 'text' => null]], 'edges' => $edges])
        ->and($saved)->toBe($this->canvases->read($this->vault, 'Board.canvas')['hash']);

    $this->putJson(route('canvases.update', [$this->vault, 'Board.canvas']), ['base_hash' => $hash, 'nodes' => [], 'edges' => []])
        ->assertConflict()
        ->assertJsonPath('message', 'This canvas changed on disk. Reload it to see the changes.');

    expect(($this->file)('Board.canvas')['nodes'])->toHaveCount(2);
});

it('refuses canvases that break the format', function (array $nodes, array $edges, string $error): void {
    $path = $this->canvases->create($this->vault, 'Board');

    $this->putJson(route('canvases.update', [$this->vault, $path]), ['base_hash' => $this->canvases->read($this->vault, $path)['hash'], 'nodes' => $nodes, 'edges' => $edges])
        ->assertJsonValidationErrors($error);
})->with([
    'unknown type' => [[canvasNode('a', ['type' => 'video'])], [], 'nodes.0.type'],
    'text card without text' => [[array_diff_key(canvasNode('a'), ['text' => true])], [], 'nodes.0.text'],
    'zero size' => [[canvasNode('a', ['width' => 0])], [], 'nodes.0.width'],
    'bad colour' => [[canvasNode('a', ['color' => 'red'])], [], 'nodes.0.color'],
    'bad side' => [[canvasNode('a'), canvasNode('b')], [['id' => 'e', 'fromNode' => 'a', 'toNode' => 'b', 'fromSide' => 'middle']], 'edges.0.fromSide'],
    'duplicate id' => [[canvasNode('a'), canvasNode('a')], [], 'nodes'],
    'edge id reused' => [[canvasNode('a'), canvasNode('b')], [['id' => 'a', 'fromNode' => 'a', 'toNode' => 'b']], 'nodes'],
    'dangling edge' => [[canvasNode('a')], [['id' => 'e', 'fromNode' => 'a', 'toNode' => 'gone']], 'edges.0'],
]);

it('only serves canvases inside the vault', function (string $path): void {
    $this->get(route('canvases.show', [$this->vault, $path]))->assertNotFound();
    $this->putJson(route('canvases.update', [$this->vault, $path]), ['base_hash' => null, 'nodes' => [], 'edges' => []])->assertNotFound();
    $this->delete(route('canvases.destroy', [$this->vault, $path]))->assertNotFound();
})->with(['missing' => ['Nope.canvas'], 'not a canvas' => ['Ideas.md'], 'outside' => ['../x.canvas'], 'dot folder' => ['.kite/x.canvas']]);

it('deletes a canvas', function (): void {
    $this->canvases->create($this->vault, 'Board');

    $this->delete(route('canvases.destroy', [$this->vault, 'Board.canvas']))
        ->assertRedirectToRoute('vaults.show', $this->vault)
        ->assertInertiaFlash('toast.message', 'Deleted “Board”.');

    expect(file_exists($this->vault->path.'/Board.canvas'))->toBeFalse();
});

it('describes files added to a canvas', function (): void {
    $files = $this->getJson(route('canvas-cards.index', [$this->vault, 'paths' => ['Ideas.md', 'attachments/photo.png', 'Nope.md']]))
        ->assertOk()
        ->json('files');

    expect($files['Ideas.md']['title'])->toBe('Ideas')
        ->and($files['attachments/photo.png']['kind'])->toBe('image')
        ->and($files['Nope.md'])->toBe(['kind' => 'missing']);

    $this->getJson(route('canvas-cards.index', $this->vault))->assertJsonValidationErrors('paths');
});

it('keeps canvas cards pointing at a note when it is renamed or moved', function (): void {
    $this->canvases->write($this->vault, 'Board.canvas', [canvasNode('n', ['type' => 'file', 'file' => 'Ideas.md'])], [], ['version' => '1.0']);
    $this->canvases->write($this->vault, 'Other.canvas', [canvasNode('n', ['type' => 'file', 'file' => 'Secret.md'])], []);
    file_put_contents($this->vault->path.'/Broken.canvas', 'nope');
    $other = file_get_contents($this->vault->path.'/Other.canvas');

    resolve(RenameNote::class)->handle(Note::query()->where('path', 'Ideas.md')->sole(), 'Archive/Old ideas');

    expect(($this->file)('Board.canvas'))->toBe(['version' => '1.0', 'nodes' => [canvasNode('n', ['type' => 'file', 'file' => 'Archive/Old ideas.md'])], 'edges' => []])
        ->and(file_get_contents($this->vault->path.'/Other.canvas'))->toBe($other)
        ->and(file_get_contents($this->vault->path.'/Broken.canvas'))->toBe('nope');
});
