<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotePropertiesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contents' => ['present', 'nullable', 'string'],
            'properties' => ['present', 'array', 'max:100'],
            'properties.*' => ['nullable'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function properties(): array
    {
        $properties = [];

        foreach ((array) $this->input('properties', []) as $key => $value) {
            $key = mb_trim((string) $key);

            if ($key !== '' && preg_match('/^[^:#\n\[\]{},]{1,100}$/u', $key) === 1) {
                $properties[$key] = $value;
            }
        }

        return $properties;
    }
}
