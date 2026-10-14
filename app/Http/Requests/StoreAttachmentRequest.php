<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AttachmentTypes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class StoreAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(AttachmentTypes $types): array
    {
        return [
            'file' => [
                'required',
                File::types($types->extensions())->max($types->maxUploadKilobytes()),
            ],
        ];
    }
}
