<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Services\ImportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public function __construct(public Import $import) {}

    public function handle(ImportProcessor $processor): void
    {
        $processor->process($this->import);
    }

    public function failed(Throwable $exception): void
    {
        $this->import->update([
            'status' => ImportStatus::Failed,
            'error' => $exception->getMessage(),
        ]);
    }
}
