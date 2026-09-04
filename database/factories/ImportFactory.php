<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'external_import_id' => fake()->unique()->uuid(),
            'sent_at' => now(),
            'status' => ImportStatus::Pending,
            'payload' => [],
            'total_offers' => 0,
            'processed_offers' => 0,
        ];
    }
}
