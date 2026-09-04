<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use App\Services\ImportProcessor;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ImportTest extends TestCase
{
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create(['code' => 'supplier-a']);
    }

    public function test_it_accepts_an_import_and_queues_processing(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/imports', $this->payload());

        $response->assertAccepted()
            ->assertJsonPath('data.status', ImportStatus::Pending->value)
            ->assertJsonPath('data.supplier', 'supplier-a')
            ->assertJsonPath('data.total_offers', 1);

        Queue::assertPushed(ProcessImport::class, 1);
    }

    public function test_it_does_not_process_the_same_import_twice(): void
    {
        Queue::fake();

        $this->postJson('/api/imports', $this->payload())->assertAccepted();
        $second = $this->postJson('/api/imports', $this->payload())->assertAccepted();

        $this->assertDatabaseCount('imports', 1);
        $this->assertSame(Import::query()->value('id'), $second->json('data.id'));

        Queue::assertPushed(ProcessImport::class, 1);
    }

    public function test_it_stores_properties_and_offers(): void
    {
        $this->postJson('/api/imports', $this->payload())->assertAccepted();

        $this->assertDatabaseHas('properties', [
            'code' => 'property-1',
            'city' => 'Barcelona',
        ]);

        $this->assertDatabaseHas('offers', [
            'supplier_id' => $this->supplier->id,
            'external_id' => 'offer-1',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'price' => 12000,
            'currency' => 'EUR',
            'available_units' => 3,
        ]);

        $import = Import::query()->firstOrFail();

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(1, $import->processed_offers);
        $this->assertNotNull($import->completed_at);
    }

    public function test_it_updates_an_offer_that_arrives_in_a_later_import(): void
    {
        $this->postJson('/api/imports', $this->payload())->assertAccepted();

        $this->postJson('/api/imports', $this->payload([
            'external_import_id' => 'import-2',
            'price' => 9900,
            'available_units' => 1,
        ]))->assertAccepted();

        $this->assertDatabaseCount('offers', 1);
        $this->assertDatabaseCount('properties', 1);

        $offer = Offer::query()->firstOrFail();

        $this->assertSame(9900, $offer->price);
        $this->assertSame(1, $offer->available_units);
    }

    public function test_it_keeps_units_that_are_already_booked(): void
    {
        $this->postJson('/api/imports', $this->payload())->assertAccepted();

        $offer = Offer::query()->firstOrFail();

        $this->postJson("/api/offers/{$offer->id}/reservations", [
            'client_reference' => 'client-1',
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
        ])->assertCreated();

        $this->assertSame(2, $offer->fresh()?->available_units);

        $this->postJson('/api/imports', $this->payload(['external_import_id' => 'import-2']))->assertAccepted();

        $offer->refresh();

        $this->assertSame(3, $offer->total_units);
        $this->assertSame(2, $offer->available_units);
    }

    public function test_it_rejects_duplicate_offer_ids_in_one_payload(): void
    {
        $payload = $this->payload();
        $payload['offers'][] = $payload['offers'][0];

        $this->postJson('/api/imports', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('offers.0.external_id');

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_it_marks_the_import_as_failed_when_processing_throws(): void
    {
        $import = Import::factory()->for($this->supplier)->create([
            'payload' => [['external_id' => 'broken-offer']],
        ]);

        try {
            app(ImportProcessor::class)->process($import);
        } catch (Throwable) {
        }

        $import->refresh();

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertNotNull($import->error);
        $this->assertDatabaseCount('offers', 0);
    }

    public function test_it_keeps_offers_of_different_suppliers_apart(): void
    {
        $other = Supplier::factory()->create(['code' => 'supplier-b']);

        $this->postJson('/api/imports', $this->payload())->assertAccepted();
        $this->postJson('/api/imports', $this->payload(['supplier' => $other->code]))->assertAccepted();

        $this->assertDatabaseCount('offers', 2);
        $this->assertSame(1, Property::query()->count());
    }

    public function test_it_validates_the_payload(): void
    {
        $this->postJson('/api/imports', $this->payload(['supplier' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('supplier');

        $this->postJson('/api/imports', $this->payload(['check_out' => '2026-09-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('offers.0.check_out');

        $this->postJson('/api/imports', ['supplier' => $this->supplier->code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_import_id', 'sent_at', 'offers']);
    }

    public function test_it_exposes_the_import_status(): void
    {
        $import = Import::factory()->for($this->supplier)->create([
            'external_import_id' => 'import-1',
            'status' => ImportStatus::Completed,
            'total_offers' => 4,
            'processed_offers' => 4,
            'completed_at' => now(),
        ]);

        $this->getJson("/api/imports/{$import->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.supplier', 'supplier-a')
            ->assertJsonPath('data.status', ImportStatus::Completed->value)
            ->assertJsonPath('data.processed_offers', 4);
    }

    public function test_it_marks_the_import_as_failed_when_the_job_fails(): void
    {
        $import = Import::factory()->for($this->supplier)->create();

        (new ProcessImport($import))->failed(new RuntimeException('supplier payload rejected'));

        $import->refresh();

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertSame('supplier payload rejected', $import->error);
    }

    public function test_it_returns_404_for_an_unknown_import(): void
    {
        $this->getJson('/api/imports/999')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $offer = [
            'external_id' => 'offer-1',
            'property' => [
                'code' => 'property-1',
                'name' => 'Sunny Apartment',
                'city' => 'Barcelona',
            ],
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 4,
            'price' => 12000,
            'currency' => 'EUR',
            'available_units' => 3,
            'expires_at' => '2026-09-20T12:00:00+00:00',
        ];

        return [
            'supplier' => $overrides['supplier'] ?? $this->supplier->code,
            'external_import_id' => $overrides['external_import_id'] ?? 'import-1',
            'sent_at' => '2026-09-10T08:00:00+00:00',
            'offers' => [array_merge($offer, array_intersect_key($overrides, $offer))],
        ];
    }
}
