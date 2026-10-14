<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class NoteStarController
{
    public function update(Request $request, Vault $vault, Note $note): RedirectResponse
    {
        $request->validate(['starred' => ['required', 'boolean']]);

        DB::table('notes')->where('id', $note->id)->update([
            'starred_at' => $request->boolean('starred') ? now() : null,
        ]);

        return back();
    }
}
