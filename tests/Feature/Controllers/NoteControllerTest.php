<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\Vault;
use Native\Desktop\Facades\Shell;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

function indexedVault(array $files): Vault
{
    $vault = vaultWithFiles($files);

    resolve(IndexVault::class)->handle($vault);

    return $vault;
}

it('shows a note in the workspace', function (): void {
    $vault = indexedVault(['Topic.md' => '# Topic', 'Other.md' => '[[Topic]]']);
    $topic = Note::query()->where('path', 'Topic.md')->sole();

    $this->get(route('notes.show', [$vault, $topic]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('note/show')
            ->where('note.id', $topic->id)
            ->where('note.contents', '# Topic')
            ->has('backlinks', 1)
            ->has('tree', 2));
});

it('does not show notes from another vault', function (): void {
    $vault = indexedVault(['A.md' => 'a']);
    $other = indexedVault(['B.md' => 'b']);

    $this->get(route('notes.show', [$vault, $other->notes()->sole()]))->assertNotFound();
});

it('creates a note', function (): void {
    $vault = indexedVault([]);

    $response = $this->post(route('notes.store', $vault), ['title' => 'New idea', 'folder' => 'Ideas']);

    $note = Note::query()->sole();

    $response->assertRedirectToRoute('notes.show', [$vault, $note]);

    expect($note->path)->toBe('Ideas/New idea.md');
});

it('creates a note at the vault root when no folder is given', function (): void {
    $vault = indexedVault([]);

    $this->post(route('notes.store', $vault), ['title' => 'Root', 'folder' => '']);

    expect(Note::query()->sole()->path)->toBe('Root.md');
});

it('validates new notes', function (array $data, string $error): void {
    $vault = indexedVault([]);

    $this->fromRoute('vaults.show', $vault)
        ->post(route('notes.store', $vault), $data)
        ->assertSessionHasErrors($error);
})->with([
    'missing title' => [[], 'title'],
    'escaping folder' => [['title' => 'x', 'folder' => '../outside'], 'folder'],
]);

it('saves note contents and returns fresh metadata', function (): void {
    $vault = indexedVault(['Note.md' => '# Old']);
    $note = Note::query()->sole();

    $this->putJson(route('notes.update', [$vault, $note]), ['contents' => "# New\n#tag"])
        ->assertOk()
        ->assertJsonPath('id', $note->id)
        ->assertJsonPath('title', 'New')
        ->assertJsonPath('tags', ['tag'])
        ->assertJsonPath('daily_date', null)
        ->assertJsonPath('html', "<h1 id=\"h-new\">New</h1>\n<p><a class=\"tag-link\" data-tag=\"tag\" href=\"/vaults/{$vault->id}/tags/tag\">#tag</a></p>\n")
        ->assertJsonPath('outline', [['level' => 1, 'text' => 'New', 'slug' => 'new', 'line' => 1]]);

    expect(file_get_contents($vault->path.'/Note.md'))->toBe("# New\n#tag");
});

it('allows saving an empty note', function (): void {
    $vault = indexedVault(['Note.md' => '# Old']);

    $this->putJson(route('notes.update', [$vault, Note::query()->sole()]), ['contents' => null])
        ->assertOk()
        ->assertJsonPath('title', 'Note');

    expect(file_get_contents($vault->path.'/Note.md'))->toBe('');
});

it('requires contents when saving', function (): void {
    $vault = indexedVault(['Note.md' => '# Old']);

    $this->putJson(route('notes.update', [$vault, Note::query()->sole()]))
        ->assertJsonValidationErrors('contents');
});

it('deletes a note', function (): void {
    $vault = indexedVault(['A.md' => 'a']);

    $this->delete(route('notes.destroy', [$vault, Note::query()->sole()]))
        ->assertRedirectToRoute('vaults.show', $vault)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Deleted “A”.']);

    expect(Note::query()->count())->toBe(0)
        ->and(file_exists($vault->path.'/A.md'))->toBeFalse();
});

it('says a deleted note went to the trash in the desktop app', function (): void {
    $vault = indexedVault(['A.md' => 'a']);
    config(['nativephp-internal.running' => true]);
    Shell::fake();

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->delete(route('notes.destroy', [$vault, Note::query()->sole()]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Moved “A” to the trash.']);

    Shell::assertTrashedFile(osPath($vault->path.'/A.md'));
});

it('saves note text exactly, including surrounding whitespace', function (): void {
    $vault = indexedVault(['A.md' => 'a']);

    $this->putJson(route('notes.update', [$vault, Note::query()->sole()]), ['contents' => "  indented\n\ntrailing\n"])->assertOk();

    expect(file_get_contents($vault->path.'/A.md'))->toBe("  indented\n\ntrailing\n");
});
