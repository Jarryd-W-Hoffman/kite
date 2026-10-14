<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateFolderRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:1024'],
            'to' => ['required', 'string', 'max:1024', 'not_regex:/[\\\\:*?"<>|\x00-\x1F]/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.not_regex' => __('Folder names cannot contain \\ : * ? " < > |'),
        ];
    }
}
