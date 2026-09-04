<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    private const CHECK_IN = '2026-10-01';

    private const CHECK_OUT = '2026-10-05';

    public function test_it_returns_the_cheapest_bookable_offer_of_a_property(): void
    {
        $property = Property::factory()->create();

        $this->offer($property, ['price' => 20000]);
        $cheapest = $this->offer($property, ['price' => 9900]);
        $this->offer($property, ['price' => 15000]);

        $this->search()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $property->code)
            ->assertJsonPath('data.0.best_offer.id', $cheapest->id)
            ->assertJsonPath('data.0.best_offer.price', 9900)
            ->assertJsonPath('data.0.best_offer.supplier', $cheapest->supplier->code);
    }

    public function test_it_ignores_cheaper_offers_that_do_not_match_the_search(): void
    {
        $property = Property::factory()->create();

        $this->offer($property, ['price' => 1000, 'check_in' => '2026-11-01']);
        $this->offer($property, ['price' => 2000, 'max_guests' => 1]);
        $this->offer($property, ['price' => 3000, 'available_units' => 0]);
        $this->offer($property, ['price' => 4000, 'expires_at' => now()->subMinute()]);
        $match = $this->offer($property, ['price' => 50000]);

        $this->search()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.best_offer.id', $match->id)
            ->assertJsonPath('data.0.best_offer.price', 50000);
    }

    public function test_it_hides_properties_without_a_bookable_offer(): void
    {
        $this->offer(Property::factory()->create(), ['check_out' => '2026-10-06']);
        $this->offer(Property::factory()->create(), ['available_units' => 0]);
        $this->offer(Property::factory()->create(), ['expires_at' => now()->subMinute()]);
        $this->offer(Property::factory()->create(), ['max_guests' => 1]);

        $this->search()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_it_filters_by_city(): void
    {
        $barcelona = Property::factory()->create(['city' => 'Barcelona']);
        $madrid = Property::factory()->create(['city' => 'Madrid']);

        $this->offer($barcelona);
        $this->offer($madrid);

        $this->search(['city' => 'Barcelona'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $barcelona->code);
    }

    public function test_it_orders_properties_by_the_cheapest_offer(): void
    {
        $expensive = Property::factory()->create();
        $cheap = Property::factory()->create();

        $this->offer($expensive, ['price' => 30000]);
        $this->offer($cheap, ['price' => 5000]);

        $this->search()
            ->assertOk()
            ->assertJsonPath('data.0.code', $cheap->code)
            ->assertJsonPath('data.1.code', $expensive->code);
    }

    public function test_it_paginates_the_results(): void
    {
        $properties = [];

        foreach (range(1, 5) as $index) {
            $property = Property::factory()->create(['city' => 'Barcelona']);
            $this->offer($property, ['price' => $index * 1000]);
            $properties[] = $property;
        }

        $first = $this->search(['city' => 'Barcelona', 'per_page' => 2])->assertOk();

        $first->assertJsonCount(2, 'data')
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('total', 5)
            ->assertJsonPath('prev', null)
            ->assertJsonPath('data.0.code', $properties[0]->code);

        $next = $first->json('next');

        $this->assertIsString($next);

        $second = $this->getJson($next)->assertOk();

        $second->assertJsonCount(2, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 5)
            ->assertJsonPath('data.0.code', $properties[2]->code)
            ->assertJsonPath('data.1.code', $properties[3]->code);

        $this->assertIsString($second->json('prev'));
    }

    public function test_it_validates_the_query_parameters(): void
    {
        $this->getJson('/api/properties')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_in', 'check_out', 'guests']);

        $this->search(['check_out' => self::CHECK_IN])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('check_out');

        $this->search(['guests' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('guests');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TestResponse<JsonResponse>
     */
    private function search(array $params = []): TestResponse
    {
        $query = http_build_query(array_merge([
            'check_in' => self::CHECK_IN,
            'check_out' => self::CHECK_OUT,
            'guests' => 2,
        ], $params));

        return $this->getJson("/api/properties?{$query}");
    }

    /** @param array<string, mixed> $attributes */
    private function offer(Property $property, array $attributes = []): Offer
    {
        return Offer::factory()->for($property)->create(array_merge([
            'check_in' => self::CHECK_IN,
            'check_out' => self::CHECK_OUT,
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDay(),
        ], $attributes));
    }
}
