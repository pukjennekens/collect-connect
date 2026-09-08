<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Services;

use App\Domain\Rebrickable\Contracts\ImportsRebrickableEntity;
use App\Domain\Rebrickable\Contracts\RebrickableDownloader;
use App\Domain\Rebrickable\Contracts\RebrickableMapper;
use App\Models\Color;
use App\Models\Inventory;
use App\Models\Minifig;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Set;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

abstract class BaseImportService implements ImportsRebrickableEntity
{
    protected int $batchSize = 500;

    public int $processedCount = 0;

    protected RebrickableMapper $mapper;

    protected ?RelationshipImportStaging $staging = null;

    public function __construct()
    {
        $this->mapper = new ($this->getMapper());
    }

    public function import(): void
    {
        $table = (new ($this->getModel()))->getTable();
        Cache::lock('rebrickable-dataset:'.$table, (int) config('rebrickable.import_lock_seconds', 14400))->block(10, function () use ($table): void {
            $this->importDataset($table);
        });
    }

    private function importDataset(string $table): void
    {
        $this->processedCount = 0;
        $this->staging = $this->isRelationship()
            ? new RelationshipImportStaging($table, array_values($this->mapper->getMapping()), (string) Str::uuid())
            : null;
        try {
            $this->staging?->prepare();
            $rows = [];
            foreach (app(RebrickableDownloader::class)->retrieveRebrickableDataFromUrl($this->getUrl()) as $row) {
                foreach (array_keys($this->mapper->getMapping()) as $column) {
                    if (! array_key_exists($column, $row)) {
                        throw new RuntimeException("Missing CSV column: {$column}");
                    }
                }
                $rows[] = $this->mapper->map($row);
                if (count($rows) >= $this->batchSize) {
                    $this->upsertRows($rows, $this->mapper->getUniqueKey());
                    $this->processedCount += count($rows);
                    $rows = [];
                }
            }
            if ($rows !== []) {
                $this->upsertRows($rows, $this->mapper->getUniqueKey());
                $this->processedCount += count($rows);
            }
            if ($this->processedCount === 0) {
                throw new RuntimeException('Refusing an empty Rebrickable dataset.');
            }
            $this->staging?->publish($this->processedCount);
        } finally {
            $this->staging?->cleanup();
        }
    }

    protected function isRelationship(): bool
    {
        return str_contains($this->getModel(), '\\Pivots\\');
    }

    /** @param list<array<string, mixed>> $rows
     * @param  string|list<string>  $uniqueKey
     */
    protected function upsertRows(array $rows, string|array $uniqueKey): void
    {
        $lookups = [
            'part_category_id' => [PartCategory::class, 'rebrickable_id'],
            'inventory_id' => [Inventory::class, 'rebrickable_id'],
            'part_id' => [Part::class, 'rebrickable_id'],
            'color_id' => [Color::class, 'rebrickable_id'],
            'minifig_id' => [Minifig::class, 'rebrickable_id'],
            'set_id' => [Set::class, 'set_num'],
        ];
        foreach ($lookups as $field => [$model, $externalKey]) {
            if (! array_key_exists($field, $rows[0])) {
                continue;
            }
            $ids = $model::query()->whereIn($externalKey, array_column($rows, $field))->pluck('id', $externalKey);
            foreach ($rows as &$row) {
                $externalId = $row[$field];
                $row[$field] = $ids[$externalId] ?? null;
                // Inventories also describe minifigure inventories with no owning set.
                if ($row[$field] === null && ! ($this->getModel() === Inventory::class && $field === 'set_id')) {
                    throw new RuntimeException("Unknown {$field}: {$externalId}");
                }
            }
            unset($row);
        }
        if (! $this->isRelationship()) {
            $this->getModel()::upsert($rows, $uniqueKey);

            return;
        }
        $this->staging?->append($rows);
    }
}
