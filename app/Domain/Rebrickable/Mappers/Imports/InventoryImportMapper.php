<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Mappers\Imports;

use App\Domain\Rebrickable\Mappers\BaseImportMapper;

class InventoryImportMapper extends BaseImportMapper
{
    protected array $mapping = [
        'id' => 'rebrickable_id',
        'set_num' => 'set_id',
        'version' => 'version',
    ];
}
