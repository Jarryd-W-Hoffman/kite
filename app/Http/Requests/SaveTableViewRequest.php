<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidColourGroupQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class SaveTableViewRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'query' => ['nullable', 'string', 'max:500', new ValidColourGroupQuery],
            'columns' => ['present', 'array', 'max:20'],
            'columns.*' => ['string', 'distinct', 'max:100'],
            'sort_key' => ['nullable', 'string', 'max:100'],
            'sort_desc' => ['boolean'],
            'layout' => ['sometimes', 'in:table,board'],
            'group_by' => ['nullable', 'string', 'max:100'],
        ];
    }
}
