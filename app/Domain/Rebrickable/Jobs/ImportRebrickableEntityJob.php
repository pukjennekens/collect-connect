<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Jobs;

use App\Domain\Rebrickable\Contracts\ImportsRebrickableEntity;
use App\Models\SyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ImportRebrickableEntityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  class-string<ImportsRebrickableEntity>  $importService
     */
    public function __construct(
        public string $importService,
    ) {}

    public int $timeout = 1800;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('rebrickable-import'))->shared()->releaseAfter(60)->expireAfter(1900)];
    }

    public function handle(): void
    {
        $run = SyncRun::create(['source' => 'rebrickable', 'status' => 'running', 'started_at' => now(), 'stats' => ['entity' => class_basename($this->importService)]]);
        try {
            $service = app($this->importService);
            $service->import();
            $run->update(['status' => 'success', 'finished_at' => now(), 'stats' => ['entity' => class_basename($this->importService), 'processed' => $service->processedCount]]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
