<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Services\Imports;

use App\Domain\Rebrickable\Mappers\Imports\InventoryMinifigImportMapper;
use App\Domain\Rebrickable\Services\BaseImportService;
use App\Models\Pivots\InventoryMinifig;

class InventoryMinifigImportService extends BaseImportService
{
    public function getUrl(): string
    {
        return 'https://cdn.rebrickable.com/media/downloads/inventory_minifigs.csv.zip';
    }

    /**
     * {@inheritDoc}
     */
    public function getModel(): string
    {
        return InventoryMinifig::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getMapper(): string
    {
        return InventoryMinifigImportMapper::class;
    }
}
