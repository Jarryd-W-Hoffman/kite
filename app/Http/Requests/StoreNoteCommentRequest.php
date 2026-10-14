<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreNoteCommentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'quote' => ['required', 'string', 'max:2000'],
            'prefix' => ['nullable', 'string', 'max:64'],
            'suffix' => ['nullable', 'string', 'max:64'],
        ];
    }
}
