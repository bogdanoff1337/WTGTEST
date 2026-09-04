<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'property_id' => Property::factory(),
            'import_id' => null,
            'external_id' => fake()->unique()->uuid(),
            'check_in' => now()->addWeek()->toDateString(),
            'check_out' => now()->addWeeks(2)->toDateString(),
            'max_guests' => 2,
            'price' => fake()->numberBetween(5000, 50000),
            'currency' => 'EUR',
            'total_units' => 5,
            'available_units' => 5,
            'expires_at' => now()->addDay(),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function soldOut(): static
    {
        return $this->state(['available_units' => 0]);
    }
}
