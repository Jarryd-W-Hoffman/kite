<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AppMenu;
use App\Services\KeyBindings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateKeyBindingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bindings' => ['required', 'array:'.implode(',', array_keys(AppMenu::COMMANDS))],
            'bindings.*' => ['present', 'nullable', 'string', 'max:40', 'regex:'.KeyBindings::PATTERN],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(KeyBindings $keyBindings): array
    {
        return [
            function (Validator $validator) use ($keyBindings): void {
                $changes = $this->bindings();
                $defaults = $keyBindings->defaults();
                $bindings = $keyBindings->all();

                foreach ($changes as $command => $accelerator) {
                    $bindings[$command] = $accelerator ?? $defaults[$command];
                }

                foreach (array_keys($changes) as $command) {
                    $accelerator = $bindings[$command];
                    $usedBy = $accelerator === '' ? null : $keyBindings->usedBy($accelerator, $command, $bindings);

                    if ($usedBy !== null) {
                        $validator->errors()->add("bindings.{$command}", __('Already used for :name.', ['name' => $usedBy]));
                    }
                }
            },
        ];
    }

    /**
     * The changes, by command: an accelerator, an empty string for none, or null for the default.
     *
     * @return array<string, string|null>
     */
    public function bindings(): array
    {
        /** @var array<string, string|null> $bindings */
        $bindings = array_map(
            fn (mixed $accelerator): ?string => is_string($accelerator) ? $accelerator : null,
            (array) $this->input('bindings', []),
        );

        return $bindings;
    }
}
