<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateNote;
use App\Models\Vault;
use App\Services\Templates;
use Illuminate\Http\RedirectResponse;

final readonly class TemplateController
{
    private const string STARTER = <<<'MD'
        ---
        created: {{date}}
        tags: []
        ---
        # {{title}}

        Made from a template on {{date:l, j F Y}} at {{time}}, in {{folder}}.

        ## Notes

        MD;

    /**
     * Start a template in the templates folder, showing the placeholders it can use.
     */
    public function store(Vault $vault, CreateNote $action, Templates $templates): RedirectResponse
    {
        $note = $action->handle($vault, 'New template', $templates->folder() ?: 'Templates', self::STARTER);

        return to_route('notes.show', [$vault, $note]);
    }
}
