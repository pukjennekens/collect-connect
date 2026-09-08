<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Mappers\Imports;

use App\Domain\Rebrickable\Mappers\BaseImportMapper;
use App\Domain\Rebrickable\Mappers\Transformers\BooleanTransformer;

class InventoryPartImportMapper extends BaseImportMapper
{
    protected string|array $uniqueKey = ['inventory_id', 'part_id', 'color_id', 'is_spare'];

    protected array $mapping = [
        'inventory_id' => 'inventory_id',
        'part_num' => 'part_id',
        'color_id' => 'color_id',
        'quantity' => 'quantity',
        'is_spare' => 'is_spare',
    ];

    protected array $transformers = [
        'is_spare' => BooleanTransformer::class,
    ];
}
