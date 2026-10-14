<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePreferencesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(Settings $settings): array
    {
        // Appearance applies instantly from its own control, so the preferences form never sends it.
        return array_diff_key($settings->rules(), ['appearance' => true]);
    }
}
