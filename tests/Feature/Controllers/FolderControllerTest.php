<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

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
