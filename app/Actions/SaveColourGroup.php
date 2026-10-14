<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GroupColour;
use App\Models\ColourGroup;
use App\Models\Vault;

final readonly class SaveColourGroup
{
    /**
     * Create a colour group at the end of the vault's list, or update an existing one in place.
     */
    public function handle(Vault $vault, string $name, string $query, GroupColour $colour, ?ColourGroup $group = null): ColourGroup
    {
        $attributes = ['name' => mb_trim($name), 'query' => mb_trim($query), 'colour' => $colour];

        if ($group instanceof ColourGroup) {
            $group->update($attributes);

            return $group;
        }

        $position = $vault->colourGroups()->max('position');

        return $vault->colourGroups()->create([
            ...$attributes,
            'position' => is_numeric($position) ? (int) $position + 1 : 0,
        ]);
    }
}
