<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateVaultPassphraseRequest extends FormRequest
{
    /**
     * A new passphrase, proven by either the current one or the recovery key.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_passphrase' => ['required_without:recovery_key', 'nullable', 'string', 'max:1024'],
            'recovery_key' => ['required_without:current_passphrase', 'nullable', 'string', 'max:128'],
            'passphrase' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
        ];
    }
}
