<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateNoteCommentRequest extends FormRequest
{
    /**
     * Any subset can be sent: edit the body, resolve or reopen, or move the anchor after the note
     * was edited around it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'resolved' => ['sometimes', 'boolean'],
            'quote' => ['sometimes', 'required', 'string', 'max:2000'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:64'],
            'suffix' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
