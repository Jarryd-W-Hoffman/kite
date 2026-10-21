<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateNoteRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contents' => ['present', 'nullable', 'string', 'max:10000000'],
            // The hash of the file the edit started from; the save is refused if it changed since.
            'base_hash' => ['nullable', 'string', 'max:64'],
        ];
    }
}
