<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Commands;

use App\Domain\Rebrickable\Contracts\ImportsRebrickableEntity;
use App\Domain\Rebrickable\Jobs\ImportRebrickableEntityJob;
use App\Domain\Rebrickable\Services\Imports\ColorImportService;
use App\Domain\Rebrickable\Services\Imports\InventoryImportService;
use App\Domain\Rebrickable\Services\Imports\InventoryMinifigImportService;
use App\Domain\Rebrickable\Services\Imports\InventoryPartImportService;
use App\Domain\Rebrickable\Services\Imports\InventorySetImportService;
use App\Domain\Rebrickable\Services\Imports\MinifigImportService;
use App\Domain\Rebrickable\Services\Imports\PartCategoryImportService;
use App\Domain\Rebrickable\Services\Imports\PartImportService;
use App\Domain\Rebrickable\Services\Imports\SetImportService;
use App\Domain\Rebrickable\Services\Imports\ThemeImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class ImportRebrickableEntityCommand extends Command
{
    protected $signature = 'rebrickable:import-entity {--entity=} {--sync : Run imports sequentially in the foreground}';

    /**
     * @var array<class-string<ImportsRebrickableEntity>, string>
     */
    protected array $importServices = [
        ThemeImportService::class => 'themes',
        PartCategoryImportService::class => 'part_categories',
        ColorImportService::class => 'colors',
        PartImportService::class => 'parts',
        MinifigImportService::class => 'minifigs',
        SetImportService::class => 'sets',
        InventoryImportService::class => 'inventories',
        InventoryPartImportService::class => 'inventory_parts',
        InventoryMinifigImportService::class => 'inventory_minifigs',
        InventorySetImportService::class => 'inventory_sets',
    ];

    public function handle(): int
    {
        $services = $this->getImportServices();
        if ($services === []) {
            $this->error('Unknown Rebrickable entity.');

            return self::FAILURE;
        }
        if ($this->option('sync')) {
            foreach ($services as $service) {
                $this->info('Importing '.$this->importServices[$service]);
                Bus::dispatchNow(new ImportRebrickableEntityJob($service));
            }
        } else {
            Bus::chain(array_map(fn (string $service) => new ImportRebrickableEntityJob($service), $services))->onQueue('imports')->dispatch();
        }

        return self::SUCCESS;
    }

    /**
     * @return list<class-string<ImportsRebrickableEntity>>
     */
    protected function getImportServices(): array
    {
        if (empty($this->option('entity'))) {
            return array_keys($this->importServices);
        }

        return array_keys(array_filter(
            $this->importServices,
            fn (string $serviceName): bool => str($this->option('entity'))->lower()->is($serviceName)
        ));
    }
}
