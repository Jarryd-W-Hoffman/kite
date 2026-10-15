<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('shows daily notes on a calendar and opens a day from it', function (): void {
    $yesterday = now()->subDay()->toDateString();
    $vault = vaultWithFiles(['Home.md' => '# Home', "Daily/{$yesterday}.md" => '# Yesterday']);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->assertAttribute("[data-date='{$yesterday}']", 'data-has-note', 'true')
        ->assertAttribute('[data-date='."'".now()->toDateString()."'".']', 'data-has-note', 'false')
        ->click("[data-date='{$yesterday}']")
        ->assertSee('Yesterday')
        ->assertAttribute("[data-date='{$yesterday}']", 'aria-current', 'date')
        ->click('[data-date='."'".now()->toDateString()."'".']')
        ->assertAttribute('[data-date='."'".now()->toDateString()."'".']', 'data-has-note', 'true')
        ->click('@toggle-calendar')
        ->assertMissing('@daily-calendar')
        ->assertNoJavaScriptErrors();

    expect(file_exists($vault->path.'/Daily/'.now()->toDateString().'.md'))->toBeTrue();
});
