<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Services\Imports;

use App\Domain\Rebrickable\Mappers\Imports\ThemeImportMapper;
use App\Domain\Rebrickable\Services\BaseImportService;
use App\Models\Theme;

class ThemeImportService extends BaseImportService
{
    public function getUrl(): string
    {
        return 'https://cdn.rebrickable.com/media/downloads/themes.csv.zip';
    }

    /**
     * {@inheritDoc}
     */
    public function getModel(): string
    {
        return Theme::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getMapper(): string
    {
        return ThemeImportMapper::class;
    }
}
