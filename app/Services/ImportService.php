<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\Supplier;

class ImportService
{
    /** @param array{supplier: string, external_import_id: string, sent_at: string, offers: array<int, array<string, mixed>>} $data */
    public function create(array $data): Import
    {
        $supplier = Supplier::where('code', $data['supplier'])->firstOrFail();

        $import = Import::firstOrCreate(
            [
                'supplier_id' => $supplier->id,
                'external_import_id' => $data['external_import_id'],
            ],
            [
                'sent_at' => $data['sent_at'],
                'status' => ImportStatus::Pending,
                'payload' => $data['offers'],
                'total_offers' => count($data['offers']),
                'processed_offers' => 0,
            ],
        );

        if ($import->wasRecentlyCreated) {
            ProcessImport::dispatch($import);
        }

        return $import;
    }
}
