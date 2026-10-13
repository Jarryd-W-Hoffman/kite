<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
final class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().'.png';

        return [
            'vault_id' => Vault::factory(),
            'path' => 'attachments/'.$name,
            'path_key' => 'attachments/'.mb_strtolower($name),
            'name_key' => mb_strtolower($name),
            'extension' => 'png',
            'size' => 0,
            'modified_at' => 0,
        ];
    }
}
