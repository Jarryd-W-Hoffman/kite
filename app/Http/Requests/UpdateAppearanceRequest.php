<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAppearanceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(Settings $settings): array
    {
        return ['appearance' => $settings->rules()['appearance']];
    }
}
