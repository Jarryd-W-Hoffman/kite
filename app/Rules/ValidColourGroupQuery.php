<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\ColourGroupQuery;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use InvalidArgumentException;

final readonly class ValidColourGroupQuery implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            ColourGroupQuery::parse(is_string($value) ? $value : '');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $fail($invalidArgumentException->getMessage());
        }
    }
}
