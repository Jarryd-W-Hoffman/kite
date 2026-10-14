<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TableView;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableView>
 */
final class TableViewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vault_id' => Vault::factory(),
            'name' => ucfirst(fake()->word()),
            'query' => '',
            'columns' => [],
            'sort_key' => null,
            'sort_desc' => false,
            'layout' => 'table',
            'group_by' => null,
        ];
    }
}
