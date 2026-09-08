<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Mappers\Imports;

use App\Domain\Rebrickable\Mappers\BaseImportMapper;

class InventorySetImportMapper extends BaseImportMapper
{
    protected string|array $uniqueKey = ['inventory_id', 'set_id'];

    protected array $mapping = [
        'inventory_id' => 'inventory_id',
        'set_num' => 'set_id',
        'quantity' => 'quantity',
    ];
}
