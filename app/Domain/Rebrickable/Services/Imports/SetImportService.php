<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Services\Imports;

use App\Domain\Rebrickable\Mappers\Imports\SetImportMapper;
use App\Domain\Rebrickable\Services\BaseImportService;
use App\Domain\Set\Jobs\ImportSetImageJob;
use App\Models\Set;

class SetImportService extends BaseImportService
{
    public function getUrl(): string
    {
        return 'https://cdn.rebrickable.com/media/downloads/sets.csv.zip';
    }

    public function getModel(): string
    {
        return Set::class;
    }

    public function getMapper(): string
    {
        return SetImportMapper::class;
    }

    public function import(): void
    {
        parent::import();

        Set::query()
            ->whereNotNull('img_url')
            ->whereDoesntHave('media', fn ($query) => $query->where('collection_name', Set::IMAGE_COLLECTION))
            ->lazyById()
            ->each(function (Set $set): void {
                if ($set->img_url !== null && $set->img_url !== '') {
                    ImportSetImageJob::dispatch($set->id, $set->img_url);
                }
            });
    }
}
