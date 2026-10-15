<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Text stored compressed with deflate. Use as `CompressedText::class.':size'` to also record the
 * text's length in characters in the `size` column, so it can be shown without reading the text.
 *
 * @implements CastsAttributes<string, string>
 */
final readonly class CompressedText implements CastsAttributes
{
    /**
     * Marks compressed text. Text never starts with a NUL byte, so values written before
     * compression are told apart and read back as they are.
     */
    public const string MARKER = "\0deflate\0";

    public function __construct(private ?string $sizeColumn = null)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        $value = is_string($value) ? $value : '';

        if (! str_starts_with($value, self::MARKER)) {
            return $value;
        }

        $text = @gzinflate(mb_substr($value, mb_strlen(self::MARKER, '8bit'), null, '8bit'));

        throw_if($text === false, RuntimeException::class, "The [{$key}] text could not be decompressed.");

        return $text;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $text = is_string($value) ? $value : '';

        return [
            $key => self::MARKER.gzdeflate($text, 6),
            ...($this->sizeColumn === null ? [] : [$this->sizeColumn => mb_strlen($text)]),
        ];
    }
}
