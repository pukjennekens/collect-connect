<?php

declare(strict_types=1);

namespace App\Domain\Bricqer\Commands;

use App\Domain\Bricqer\Jobs\SyncBricqerOrdersJob;
use Illuminate\Console\Command;

class SyncBricqerOrdersCommand extends Command
{
    protected $signature = 'bricqer:sync-orders';

    protected $description = 'Queue status, tracking and invoice synchronization for webshop orders.';

    public function handle(): int
    {
        SyncBricqerOrdersJob::dispatch();

        return self::SUCCESS;
    }
}
