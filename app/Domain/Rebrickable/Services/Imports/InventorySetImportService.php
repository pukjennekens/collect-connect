<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Services\Imports;

use App\Domain\Rebrickable\Mappers\Imports\InventorySetImportMapper;
use App\Domain\Rebrickable\Services\BaseImportService;
use App\Models\Pivots\InventorySet;

class InventorySetImportService extends BaseImportService
{
    public function getUrl(): string
    {
        return 'https://cdn.rebrickable.com/media/downloads/inventory_sets.csv.zip';
    }

    /**
     * {@inheritDoc}
     */
    public function getModel(): string
    {
        return InventorySet::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getMapper(): string
    {
        return InventorySetImportMapper::class;
    }
}
