<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Services\Imports;

use App\Domain\Rebrickable\Mappers\Imports\InventoryPartImportMapper;
use App\Domain\Rebrickable\Services\BaseImportService;
use App\Models\Pivots\InventoryPart;

class InventoryPartImportService extends BaseImportService
{
    public function getUrl(): string
    {
        return 'https://cdn.rebrickable.com/media/downloads/inventory_parts.csv.zip';
    }

    /**
     * {@inheritDoc}
     */
    public function getModel(): string
    {
        return InventoryPart::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getMapper(): string
    {
        return InventoryPartImportMapper::class;
    }
}
