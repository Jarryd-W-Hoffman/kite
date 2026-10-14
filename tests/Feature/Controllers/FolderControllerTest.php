<?php

declare(strict_types=1);

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Vault;
use Native\Desktop\Facades\Shell;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

it('creates folders at the root and inside other folders', function (): void {
    $vault = vaultWithFiles(['Projects/Plan.md' => '# Plan']);

    $this->fromRoute('vaults.index')
        ->post(route('folders.store', $vault), ['name' => 'Archive'])
        ->assertRedirectToRoute('vaults.index')
        ->assertSessionHasNoErrors();

    $this->post(route('folders.store', $vault), ['name' => '2026/Q1', 'parent' => 'Projects'])
        ->assertSessionHasNoErrors();

    expect(is_dir($vault->path.'/Archive'))->toBeTrue()
        ->and(is_dir($vault->path.'/Projects/2026/Q1'))->toBeTrue();
});

it('shows empty folders in the workspace tree', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    $this->post(route('folders.store', $vault), ['name' => 'Empty']);

    $this->get(route('notes.show', [$vault, Note::query()->sole()]))
        ->assertInertia(fn ($page) => $page->where('folders', ['Empty']));
});

it('validates new folders', function (array $data, string $message): void {
    config(['kite.ignored_folders' => ['node_modules']]);

    $vault = vaultWithFiles(['Taken/Note.md' => '# Note', 'File.md' => '# File']);

    $this->fromRoute('vaults.index')
        ->post(route('folders.store', $vault), $data)
        ->assertSessionHasErrors(['name' => $message]);
})->with([
    'missing name' => [[], 'The name field is required.'],
    'reserved characters' => [['name' => 'a:b'], 'Folder names cannot contain \ : * ? " < > |'],
    'existing folder' => [['name' => 'Taken'], 'A folder or file with that name already exists.'],
    'existing file' => [['name' => 'File.md'], 'A folder or file with that name already exists.'],
    'escaping the vault' => [['name' => '../outside'], 'That folder name is not valid.'],
    'escaping via parent' => [['name' => 'x', 'parent' => '../..'], 'That folder name is not valid.'],
    'dot folder' => [['name' => '.hidden'], 'That folder name is not valid.'],
    'ignored folder' => [['name' => 'lib/node_modules'], 'That folder is ignored in Preferences, so it would not be shown.'],
]);

function folderVault(): Vault
{
    $vault = vaultWithFiles([
        'Home.md' => "# Home\n\n[[Projects/Plan]] [[Deep]] ![[img.png]]",
        'Projects/Plan.md' => '# Plan',
        'Projects/sub/Deep.md' => '# Deep',
        'Projects/img.png' => 'png',
        'Projects/.hidden' => 'h',
        'Archive/Old.md' => '# Old',
    ]);

    resolve(IndexVault::class)->handle($vault);
    resolve(IndexAttachments::class)->handle($vault);

    return $vault;
}

it('renames a folder, moving everything in it and rewriting path links', function (): void {
    $vault = folderVault();

    $this->fromRoute('vaults.index')
        ->put(route('folders.update', $vault), ['path' => 'Projects', 'to' => 'Work'])
        ->assertRedirectToRoute('vaults.index')
        ->assertSessionHasNoErrors();

    expect(Note::query()->orderBy('path')->pluck('path')->all())->toBe(['Archive/Old.md', 'Home.md', 'Work/Plan.md', 'Work/sub/Deep.md'])
        ->and(Attachment::query()->pluck('path')->all())->toBe(['Work/img.png'])
        ->and(is_dir($vault->path.'/Projects'))->toBeFalse()
        ->and(file_exists($vault->path.'/Work/.hidden'))->toBeTrue()
        ->and(file_get_contents($vault->path.'/Home.md'))->toContain('[[Work/Plan]] [[Deep]] ![[img.png]]')
        ->and(NoteLink::query()->whereNull('target_note_id')->count())->toBe(0);
});

it('moves a folder into another one and handles case-only renames', function (): void {
    $vault = folderVault();

    $this->put(route('folders.update', $vault), ['path' => 'Projects', 'to' => 'Archive/Projects'])->assertSessionHasNoErrors();
    $this->put(route('folders.update', $vault), ['path' => 'Archive', 'to' => 'archive'])->assertSessionHasNoErrors();
    $this->put(route('folders.update', $vault), ['path' => 'archive', 'to' => 'archive'])->assertSessionHasNoErrors();

    expect(Note::query()->orderBy('path')->pluck('path')->all())->toBe(['Home.md', 'archive/Old.md', 'archive/Projects/Plan.md', 'archive/Projects/sub/Deep.md'])
        ->and(scandir($vault->path))->toContain('archive')->not->toContain('Archive');
});

it('validates folder renames', function (array $data, string $field, string $message): void {
    config(['kite.ignored_folders' => ['node_modules']]);

    $vault = folderVault();

    $this->fromRoute('vaults.index')
        ->put(route('folders.update', $vault), $data)
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'missing target' => [['path' => 'Projects'], 'to', 'The to field is required.'],
    'reserved characters' => [['path' => 'Projects', 'to' => 'a|b'], 'to', 'Folder names cannot contain \ : * ? " < > |'],
    'existing folder' => [['path' => 'Projects', 'to' => 'Archive'], 'to', 'A folder or file with that name already exists.'],
    'existing file' => [['path' => 'Projects', 'to' => 'Home.md'], 'to', 'A folder or file with that name already exists.'],
    'inside itself' => [['path' => 'Projects', 'to' => 'Projects/sub/Projects'], 'to', 'A folder cannot be moved inside itself.'],
    'ignored' => [['path' => 'Projects', 'to' => 'node_modules'], 'to', 'That folder is ignored in Preferences, so it would not be shown.'],
    'missing folder' => [['path' => 'Nope', 'to' => 'Other'], 'to', 'That folder no longer exists.'],
    'escaping' => [['path' => 'Projects', 'to' => '../Out'], 'to', 'That folder name is not valid.'],
]);

it('deletes a folder and leaves links to its notes unresolved', function (): void {
    $vault = folderVault();

    $this->fromRoute('vaults.index')
        ->delete(route('folders.destroy', $vault), ['path' => 'Projects'])
        ->assertRedirectToRoute('vaults.index')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Folder deleted.']);

    expect(Note::query()->orderBy('path')->pluck('path')->all())->toBe(['Archive/Old.md', 'Home.md'])
        ->and(Attachment::query()->count())->toBe(0)
        ->and(is_dir($vault->path.'/Projects'))->toBeFalse()
        ->and(NoteLink::query()->whereNull('target_note_id')->pluck('target')->sort()->values()->all())->toBe(['Deep', 'Projects/Plan']);
});

it('leaves the deleted folder when the open note was inside it', function (): void {
    $vault = folderVault();

    $this->fromRoute('notes.show', [$vault, Note::query()->where('path', 'Projects/Plan.md')->sole()])
        ->delete(route('folders.destroy', $vault), ['path' => 'Projects', 'leave' => true])
        ->assertRedirectToRoute('vaults.show', $vault);
});

it('sends deleted folders to the trash in the desktop app', function (): void {
    $vault = folderVault();
    config(['nativephp-internal.running' => true]);
    Shell::fake();

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->delete(route('folders.destroy', $vault), ['path' => 'Projects'])
        ->assertSessionHasNoErrors();

    Shell::assertTrashedFile($vault->path.'/Projects');
});

it('validates folder deletes', function (array $data, string $message): void {
    $vault = folderVault();

    $this->fromRoute('vaults.index')
        ->delete(route('folders.destroy', $vault), $data)
        ->assertSessionHasErrors(['path' => $message]);
})->with([
    'missing path' => [[], 'The path field is required.'],
    'missing folder' => [['path' => 'Nope'], 'That folder no longer exists.'],
    'escaping' => [['path' => '..'], 'That folder name is not valid.'],
]);
