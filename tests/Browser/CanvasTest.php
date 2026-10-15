<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\Vault;
use App\Services\Canvases;

function canvasFile(Vault $vault, string $path): array
{
    return json_decode((string) file_get_contents($vault->path.'/'.$path), true);
}

it('builds a canvas of cards and notes, connects them, and saves it as JSON Canvas', function (): void {
    $vault = vaultWithFiles(['Ideas.md' => "# Ideas\n\nSome **bold** thinking."]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@new-canvas')
        ->assertSeeIn('@canvas-name', 'Untitled')
        ->assertSee('Double-click to add a card')
        ->click('@canvas-add-text')
        ->type('@canvas-card-editor', '## Plan')
        ->keys('@canvas-card-editor', 'Escape')
        ->assertSeeIn('[data-card-type=text] h2', 'Plan')
        ->click('@canvas-add-note')
        ->type('[data-test=canvas-note-picker] input', 'Ideas')
        ->click('@canvas-note-option')
        ->assertSeeIn('[data-card-type=file]', 'Some bold thinking.')
        ->drag('[data-card-type=text] [data-test=canvas-connect-right]', '[data-card-type=file]')
        ->assertCount('@canvas-edge', 1)
        ->assertSeeIn('@canvas-save-state', 'Saved');

    $canvas = canvasFile($vault, 'Untitled.canvas');
    [$text, $file] = $canvas['nodes'];

    expect($text)->toMatchArray(['type' => 'text', 'text' => '## Plan'])
        ->and($file)->toMatchArray(['type' => 'file', 'file' => 'Ideas.md'])
        ->and($canvas['edges'])->toHaveCount(1)
        ->and($canvas['edges'][0])->toMatchArray(['fromNode' => $text['id'], 'fromSide' => 'right', 'toNode' => $file['id'], 'toEnd' => 'arrow']);

    $page->keys('@canvas-board', 'Control+z')
        ->assertCount('@canvas-edge', 0)
        ->assertSeeIn('@canvas-save-state', 'Saved')
        ->keys('@canvas-board', 'Control+Shift+z')
        ->assertCount('@canvas-edge', 1)
        ->click('[data-card-type=text]')
        ->click('@canvas-color-4')
        ->assertSeeIn('@canvas-save-state', 'Saved')
        ->assertNoJavaScriptErrors();

    expect(canvasFile($vault, 'Untitled.canvas')['nodes'][0]['color'])->toBe('4');

    $page->click('@delete-canvas')
        ->click('Delete canvas')
        ->assertSee('Deleted “Untitled”.')
        ->assertDontSee('@canvas-link');

    expect(file_exists($vault->path.'/Untitled.canvas'))->toBeFalse();
});

it('shows a canvas made elsewhere, and stops saving when it changes on disk', function (): void {
    $vault = vaultWithFiles(['Ideas.md' => '# Ideas', 'pics/sky.png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')]);
    resolve(IndexVault::class)->handle($vault);
    resolve(Canvases::class)->write($vault, 'Board.canvas', [
        ['id' => 'g', 'type' => 'group', 'label' => 'Research', 'x' => -40, 'y' => -60, 'width' => 900, 'height' => 500, 'color' => '5'],
        ['id' => 't', 'type' => 'text', 'text' => 'Start **here**', 'x' => 0, 'y' => 0, 'width' => 240, 'height' => 100],
        ['id' => 'n', 'type' => 'file', 'file' => 'Ideas.md', 'x' => 400, 'y' => 0, 'width' => 300, 'height' => 200],
        ['id' => 'i', 'type' => 'file', 'file' => 'pics/sky.png', 'x' => 0, 'y' => 200, 'width' => 200, 'height' => 120],
        ['id' => 'l', 'type' => 'link', 'url' => 'https://example.com/page', 'x' => 400, 'y' => 260, 'width' => 300, 'height' => 100],
        ['id' => 'm', 'type' => 'file', 'file' => 'Gone.md', 'x' => 1000, 'y' => 0, 'width' => 200, 'height' => 80],
    ], [['id' => 'e', 'fromNode' => 't', 'fromSide' => 'right', 'toNode' => 'n', 'toSide' => 'left', 'label' => 'leads to']], ['meta' => 'kept']);

    $page = visit(route('canvases.show', [$vault, 'Board.canvas']))
        ->assertSeeIn('@canvas-group', 'Research')
        ->assertSeeIn('[data-card-type=text] strong', 'here')
        ->assertSeeIn('[data-card-type=file] h1', 'Ideas')
        ->assertVisible('[data-card-type=file] img')
        ->assertSeeIn('[data-card-type=link]', 'https://example.com/page')
        ->assertSee('Not found: Gone.md')
        ->assertSeeIn('@canvas-edge-label', 'leads to')
        ->assertCount('@canvas-edge', 1)
        ->assertNoJavaScriptErrors();

    file_put_contents($vault->path.'/Board.canvas', '{"nodes": [], "edges": []}');

    $page->click('@canvas-add-text')
        ->type('@canvas-card-editor', 'Mine')
        ->keys('@canvas-card-editor', 'Escape')
        ->assertSeeIn('@canvas-save-state', 'Changed on disk')
        ->assertSee('This canvas changed on disk.');

    expect(file_get_contents($vault->path.'/Board.canvas'))->toBe('{"nodes": [], "edges": []}');
});

it('explains a canvas it can’t read instead of opening it', function (): void {
    $vault = vaultWithFiles(['Broken.canvas' => '{not json']);

    visit(route('canvases.show', [$vault, 'Broken.canvas']))
        ->assertSee('This canvas can’t be read')
        ->assertMissing('@canvas-board')
        ->assertNoJavaScriptErrors();
});
