<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Vault;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LinkMentionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $vault = $this->route('vault');
        assert($vault instanceof Vault);

        return [
            'source_id' => ['required', 'integer', Rule::exists('notes', 'id')->where('vault_id', $vault->id)],
            'line' => ['required', 'integer', 'min:1'],
        ];
    }
}
