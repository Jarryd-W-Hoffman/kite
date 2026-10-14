<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Note;
use App\Models\Vault;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreNoteMergeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Vault $vault */
        $vault = $this->route('vault');
        /** @var Note $note */
        $note = $this->route('note');

        return [
            'into' => [
                'required',
                'integer',
                Rule::exists('notes', 'id')->where('vault_id', $vault->id),
                Rule::notIn([$note->id]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'into.not_in' => __('A note cannot be merged into itself.'),
        ];
    }
}
