<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GroupColour;
use App\Rules\ValidColourGroupQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateColourGroupRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'query' => ['required', 'string', 'max:500', new ValidColourGroupQuery],
            'colour' => ['required', Rule::enum(GroupColour::class)],
        ];
    }
}
