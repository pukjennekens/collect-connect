<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Mappers\Imports;

use App\Domain\Rebrickable\Mappers\BaseImportMapper;

class ThemeImportMapper extends BaseImportMapper
{
    protected array $mapping = ['id' => 'rebrickable_id', 'name' => 'name', 'parent_id' => 'parent_id'];

    protected array $defaults = ['parent_id' => null];
}
