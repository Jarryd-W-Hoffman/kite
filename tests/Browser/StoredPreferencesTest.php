<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('ignores stored preferences it can’t read', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note', 'Folder/Inner.md' => '# Inner']);
    resolve(IndexVault::class)->handle($vault);
    $note = Note::query()->where('path', 'Note.md')->sole();

    $page = visit(route('notes.show', [$vault, $note]))->assertSeeIn('@note-editor', 'Note');

    $page->script(<<<JS
    (() => {
        localStorage.setItem('kite:panel', 'sideways');
        localStorage.setItem('kite:view-mode', 'hologram');
        localStorage.setItem('kite:split:{$vault->id}', 'not a note');
        localStorage.setItem('kite:comments-open', '{');
        localStorage.setItem('kite:calendar-open', 'maybe');
        localStorage.setItem('kite:panel-collapsed', '{"Backlinks": true}');
        localStorage.setItem('kite:collapsed:{$vault->id}', '[1, 2');
        localStorage.setItem('kite:tabs:{$vault->id}', '{"tabs": [{"id": "x"}], "active": 1}');
        localStorage.setItem('appearance', 'neon');
    })()
    JS);

    $page->refresh()
        ->assertSeeIn('@note-editor', 'Note')
        ->assertSee('Inner')
        ->assertScript(JS_ERRORS, '[]');
});
