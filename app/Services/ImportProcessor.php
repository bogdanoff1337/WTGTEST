<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportProcessor
{
    public function process(Import $import): void
    {
        $import->update(['status' => ImportStatus::Processing]);

        try {
            DB::transaction(function () use ($import) {
                $processed = 0;

                foreach ($import->payload as $offer) {
                    $this->storeOffer($import, $offer);
                    $processed++;
                }

                $import->update([
                    'status' => ImportStatus::Completed,
                    'total_offers' => count($import->payload),
                    'processed_offers' => $processed,
                    'error' => null,
                    'completed_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $import->update([
                'status' => ImportStatus::Failed,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function storeOffer(Import $import, array $data): void
    {
        $property = Property::updateOrCreate(
            ['code' => $data['property']['code']],
            [
                'name' => $data['property']['name'],
                'city' => $data['property']['city'],
            ],
        );

        $offer = Offer::query()
            ->where('supplier_id', $import->supplier_id)
            ->where('external_id', $data['external_id'])
            ->lockForUpdate()
            ->first() ?? new Offer;

        $units = (int) $data['available_units'];
        $consumed = $offer->exists ? max(0, $offer->total_units - $offer->available_units) : 0;

        $offer->fill([
            'supplier_id' => $import->supplier_id,
            'external_id' => $data['external_id'],
            'property_id' => $property->id,
            'import_id' => $import->id,
            'check_in' => CarbonImmutable::parse($data['check_in'])->toDateString(),
            'check_out' => CarbonImmutable::parse($data['check_out'])->toDateString(),
            'max_guests' => $data['max_guests'],
            'price' => $data['price'],
            'currency' => strtoupper($data['currency']),
            'total_units' => $units,
            'available_units' => max(0, $units - $consumed),
            'expires_at' => CarbonImmutable::parse($data['expires_at']),
        ])->save();
    }
}
