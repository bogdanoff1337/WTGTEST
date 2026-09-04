<?php

namespace Tests\Feature;

use App\Exceptions\DuplicateReservationException;
use App\Models\Offer;
use App\Models\Reservation;
use App\Services\ReservationService;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    public function test_it_books_an_offer_and_consumes_a_unit(): void
    {
        $offer = Offer::factory()->create(['available_units' => 3]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.client_reference', 'client-1')
            ->assertJsonPath('data.customer_email', 'ada@example.com')
            ->assertJsonPath('data.offer.id', $offer->id);

        $this->assertSame(2, $offer->fresh()?->available_units);
        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'client_reference' => 'client-1',
        ]);
    }

    public function test_it_rejects_a_booking_when_no_units_are_left(): void
    {
        $offer = Offer::factory()->soldOut()->create();

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertConflict()
            ->assertJsonPath('message', 'This offer has no available units left.');

        $this->assertDatabaseCount('reservations', 0);
        $this->assertSame(0, $offer->fresh()?->available_units);
    }

    public function test_it_rejects_a_booking_of_an_expired_offer(): void
    {
        $offer = Offer::factory()->expired()->create(['available_units' => 5]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertConflict()
            ->assertJsonPath('message', 'This offer has expired.');

        $this->assertDatabaseCount('reservations', 0);
        $this->assertSame(5, $offer->fresh()?->available_units);
    }

    public function test_only_one_booking_wins_the_last_unit(): void
    {
        $offer = Offer::factory()->create(['available_units' => 1]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertCreated();

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload(['client_reference' => 'client-2']))
            ->assertConflict();

        $this->assertSame(0, $offer->fresh()?->available_units);
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_it_does_not_consume_a_unit_when_the_reservation_cannot_be_stored(): void
    {
        Reservation::factory()->create(['client_reference' => 'client-1']);

        $offer = Offer::factory()->create(['available_units' => 2]);

        $this->expectException(DuplicateReservationException::class);

        try {
            app(ReservationService::class)->reserve($offer, $this->payload());
        } finally {
            $this->assertSame(2, $offer->fresh()?->available_units);
            $this->assertDatabaseCount('reservations', 1);
        }
    }

    public function test_it_rejects_a_duplicate_client_reference(): void
    {
        $offer = Offer::factory()->create(['available_units' => 5]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())->assertCreated();

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('client_reference');

        $this->assertSame(4, $offer->fresh()?->available_units);
    }

    public function test_it_validates_the_payload(): void
    {
        $offer = Offer::factory()->create();

        $this->postJson("/api/offers/{$offer->id}/reservations", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_reference', 'customer_name', 'customer_email']);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload(['customer_email' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('customer_email');
    }

    public function test_it_returns_404_for_an_unknown_offer(): void
    {
        $this->postJson('/api/offers/999/reservations', $this->payload())->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_reference' => 'client-1',
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
        ], $overrides);
    }
}
