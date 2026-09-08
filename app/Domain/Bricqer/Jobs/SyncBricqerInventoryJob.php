<?php

declare(strict_types=1);

namespace App\Domain\Bricqer\Jobs;

use App\Domain\Product\Services\StockService;
use App\Integrations\Bricqer\DataTransferObjects\UnconsolidatedInventory\InventoryItem;
use App\Integrations\Bricqer\Facades\Bricqer;
use App\Models\Color;
use App\Models\Minifig;
use App\Models\Order;
use App\Models\Part;
use App\Models\Pivots\PartColor;
use App\Models\Product;
use App\Models\SyncRun;
use App\Support\BulkCaseUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncBricqerInventoryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var int<1, max>
     */
    protected int $chunkSize = 1000;

    /**
     * @var Collection<covariant string, int>
     */
    protected Collection $colorIdMap;

    /**
     * @var list<int>
     */
    protected array $syncedProductIds = [];

    public int $timeout = 1800;

    public int $tries = 3;

    /**
     * @return array{
     *     found: int,
     *     color_unmatched: int,
     *     item_not_found: int,
     *     zeroed: int,
     *     minifig_definitions_updated: int,
     *     part_definitions_updated: int
     * }
     */
    public function handle(): array
    {
        return Cache::lock(StockService::INTEGRATION_LOCK, 1900)->block(10, fn (): array => $this->synchronize());
    }

    /**
     * @return array{
     *     found: int,
     *     color_unmatched: int,
     *     item_not_found: int,
     *     zeroed: int,
     *     minifig_definitions_updated: int,
     *     part_definitions_updated: int
     * }
     */
    private function synchronize(): array
    {
        $run = SyncRun::query()->create([
            'source' => 'bricqer_inventory',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $snapshotStarted = now();
            $this->colorIdMap = $this->retrieveColorIdMap();
            $this->syncedProductIds = [];

            $previouslyOutOfStock = Product::query()
                ->where('stock', '<=', 0)
                ->pluck('id')
                ->all();

            $stats = [
                'found' => 0,
                'color_unmatched' => 0,
                'item_not_found' => 0,
                'zeroed' => 0,
                'minifig_definitions_updated' => 0,
                'part_definitions_updated' => 0,
            ];

            $consolidated = $this->consolidate();

            // Never wipe the shop if the feed is empty/misconfigured.
            if ($consolidated === []) {
                throw new RuntimeException(
                    'Bricqer unconsolidated inventory returned no new-condition P/M rows; refusing to zero stock.',
                );
            }

            // Resolve bricklink ids to local entity ids once for the whole feed; both the
            // definition-id sync below and the per-chunk upserts reuse these same maps.
            $partIds = $this->mapBricklinkIds(
                array_column(array_filter($consolidated, fn (array $r): bool => $r['item_type'] === 'P'), 'item_id'),
                Part::class,
            );
            $minifigIds = $this->mapBricklinkIds(
                array_column(array_filter($consolidated, fn (array $r): bool => $r['item_type'] === 'M'), 'item_id'),
                Minifig::class,
            );

            // Aggregate definition ids across the full feed first so chunk order
            // cannot regress a higher definition id written by an earlier chunk.
            $stats['minifig_definitions_updated'] = $this->applyDefinitionIdsFromInventory(
                $consolidated,
                $minifigIds,
                'M',
                Minifig::class,
            );
            $stats['part_definitions_updated'] = $this->applyDefinitionIdsFromInventory(
                $consolidated,
                $partIds,
                'P',
                Part::class,
            );

            DB::transaction(function () use ($consolidated, $partIds, $minifigIds, $snapshotStarted, &$stats): void {
                // An imported order is not proof that Bricqer has deducted stock.
                // Keep local holds through open/processing states; picker completion
                // must precede the snapshot before handing inventory ownership back.
                Order::query()->where('stock_held', true)->whereNotNull('exported_at')
                    ->where('exported_at', '<', $snapshotStarted)->where('sync_status', 'synced')
                    ->whereIn('fulfilment_status', ['packed', 'shipped', 'completed'])
                    ->update(['stock_held' => false]);
                foreach (array_chunk($consolidated, $this->chunkSize) as $chunk) {
                    $chunkStats = $this->upsertProducts($chunk, $partIds, $minifigIds);
                    $stats['found'] += $chunkStats['found'];
                    $stats['color_unmatched'] += $chunkStats['color_unmatched'];
                    $stats['item_not_found'] += $chunkStats['item_not_found'];
                }

                $stats['zeroed'] = $this->zeroStockForMissingProducts();
            }, 3);

            $restockedIds = Product::query()
                ->whereIn('id', $previouslyOutOfStock)
                ->where('stock', '>', 0)
                ->get(['id'])->map(fn (Product $product): int => $product->id)->values()->all();

            if ($restockedIds !== []) {
                DispatchStockNotificationsJob::dispatch(array_values($restockedIds));
            }

            $run->update([
                'status' => 'succeeded',
                'stats' => $stats,
                'finished_at' => now(),
            ]);

            return $stats;
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @return Collection<covariant string, int>
     */
    protected function retrieveColorIdMap(): Collection
    {
        return Color::query()
            ->whereNotNull('bricklink_color_id')
            ->pluck('id', 'bricklink_color_id')
            ->mapWithKeys(fn (int $id, string|int $bricklinkColorId): array => [(string) $bricklinkColorId => $id]);
    }

    /**
     * @return list<array{item_type: string, item_id: string, color_id: string, stock: int, price: int, definition_id: string, title: string, definitions: array<int|string, int>}>
     */
    protected function consolidate(): array
    {
        $consolidated = [];

        /** @var InventoryItem $item */
        foreach (Bricqer::lego()->report()->unconsolidatedInventory()->get() as $item) {
            if (! in_array($item->itemType, ['P', 'M'], true)) {
                continue;
            }

            if ($item->condition !== 'N') {
                continue;
            }

            $colorId = $item->colorId !== '' ? $item->colorId : '0';
            $key = "{$item->itemType}_{$item->itemId}_{$colorId}";
            if ($item->remainingQuantity < 0 || ! is_finite($item->price) || $item->price < 0) {
                throw new RuntimeException('Invalid Bricqer stock or price; refusing to apply the feed.');
            }
            $priceInCents = (int) round($item->price * 100);
            $definitionId = trim($item->definitionId);

            if (! isset($consolidated[$key])) {
                $consolidated[$key] = [
                    'item_type' => $item->itemType,
                    'item_id' => $item->itemId,
                    'color_id' => $colorId,
                    'stock' => $item->remainingQuantity,
                    'price' => $priceInCents,
                    'definition_id' => $definitionId,
                    'title' => $item->description,
                    'definitions' => ctype_digit($definitionId) && (int) $definitionId > 0 ? [$definitionId => $item->remainingQuantity] : [],
                ];

                continue;
            }

            $consolidated[$key]['stock'] += $item->remainingQuantity;
            if (ctype_digit($definitionId) && (int) $definitionId > 0) {
                $consolidated[$key]['definitions'][$definitionId] = ($consolidated[$key]['definitions'][$definitionId] ?? 0) + $item->remainingQuantity;
            }
            $consolidated[$key]['price'] = max($consolidated[$key]['price'], $priceInCents);
            $consolidated[$key]['definition_id'] = $this->preferDefinitionId(
                $consolidated[$key]['definition_id'],
                $definitionId,
            );
        }

        return array_values($consolidated);
    }

    /**
     * Keep the best definition id when multiple lots consolidate: prefer any
     * meaningful non-empty value (treat "0" as unset), and when both are set
     * prefer the higher numeric id (newer Bricqer definition). Never regress
     * a real id to "0".
     */
    protected function preferDefinitionId(string $current, string $candidate): string
    {
        $current = $this->normalizeDefinitionId($current);
        $candidate = $this->normalizeDefinitionId($candidate);

        if ($candidate === '') {
            return $current;
        }

        if ($current === '') {
            return $candidate;
        }

        if (ctype_digit($current) && ctype_digit($candidate)) {
            return (int) $candidate > (int) $current ? $candidate : $current;
        }

        return $candidate;
    }

    protected function normalizeDefinitionId(string $definitionId): string
    {
        $definitionId = trim($definitionId);

        return $definitionId === '0' ? '' : $definitionId;
    }

    /**
     * @param  list<array{item_type: string, item_id: string, color_id: string, stock: int, price: int, definition_id: string, title: string, definitions: array<int|string, int>}>  $chunk
     * @param  Collection<covariant string, int>  $partIds
     * @param  Collection<covariant string, int>  $minifigIds
     * @return array{found: int, color_unmatched: int, item_not_found: int}
     */
    protected function upsertProducts(array $chunk, Collection $partIds, Collection $minifigIds): array
    {
        $stats = [
            'found' => 0,
            'color_unmatched' => 0,
            'item_not_found' => 0,
        ];

        DB::transaction(function () use ($chunk, $partIds, $minifigIds, &$stats): void {
            $partMorph = (new Part)->getMorphClass();
            $minifigMorph = (new Minifig)->getMorphClass();
            $existingPartColorDefinitions = $this->existingPartColorDefinitions($chunk, $partIds);

            foreach ($chunk as $row) {
                $colorId = $this->colorIdMap->get((string) $row['color_id']);

                if (! $colorId) {
                    $stats['color_unmatched']++;

                    continue;
                }

                if ($row['item_type'] === 'P') {
                    $entityId = $partIds->get(strtolower($row['item_id']));
                    $morph = $partMorph;
                } else {
                    $entityId = $minifigIds->get(strtolower($row['item_id']));
                    $morph = $minifigMorph;
                }

                if (! $entityId) {
                    $stats['item_not_found']++;

                    continue;
                }

                $product = Product::query()->firstOrNew([
                    'productable_type' => $morph,
                    'productable_id' => $entityId,
                    'color_id' => $colorId,
                ]);
                if ($product->exists) {
                    $product = Product::query()->lockForUpdate()->findOrFail($product->id);
                }
                $product->fill([
                    'stock' => max(0, $row['stock'] - ($product->exists ? app(StockService::class)->heldQuantity($product) : 0)),
                    'bricqer_stock' => $row['stock'],
                    'price' => $row['price'],
                    'commerce_title' => $row['title'] ?: null,
                    'source_definitions' => collect($row['definitions'])->map(fn (int $quantity, int|string $id): array => ['definition_id' => (int) $id, 'quantity' => $quantity])->values()->all(),
                ]);
                if ($product->isDirty()) {
                    $product->save();
                }

                $this->syncedProductIds[] = $product->id;
                $stats['found']++;

                if ($row['item_type'] === 'P') {
                    $partColorKey = ['part_id' => $entityId, 'color_id' => $colorId];
                    $partColorAttributes = [];

                    if ($row['definition_id'] !== '') {
                        $existing = $existingPartColorDefinitions->get("{$entityId}-{$colorId}");

                        $partColorAttributes['bricqer_definition_id'] = $this->preferDefinitionId(
                            (string) ($existing ?? ''),
                            $row['definition_id'],
                        );
                    }

                    // Ensure pivot exists even without a definition id (stock/catalog path).
                    if ($partColorAttributes === []) {
                        PartColor::query()->firstOrCreate($partColorKey, ['bricqer_definition_id' => '0']);
                    } else {
                        PartColor::updateOrCreate($partColorKey, $partColorAttributes);
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * Batch-fetches existing PartColor.bricqer_definition_id values for every part
     * this chunk may touch, so upsertProducts() doesn't run a SELECT per row.
     *
     * @param  list<array{item_type: string, item_id: string, color_id: string, stock: int, price: int, definition_id: string, title: string, definitions: array<int|string, int>}>  $chunk
     * @param  Collection<covariant string, int>  $partIds
     * @return Collection<covariant string, string>
     */
    protected function existingPartColorDefinitions(array $chunk, Collection $partIds): Collection
    {
        $entityIds = [];

        foreach ($chunk as $row) {
            if ($row['item_type'] !== 'P' || $row['definition_id'] === '') {
                continue;
            }

            $entityId = $partIds->get(strtolower($row['item_id']));

            if ($entityId !== null) {
                $entityIds[] = $entityId;
            }
        }

        if ($entityIds === []) {
            return collect();
        }

        return PartColor::query()
            ->whereIn('part_id', array_unique($entityIds))
            ->get(['part_id', 'color_id', 'bricqer_definition_id'])
            ->mapWithKeys(fn (PartColor $partColor): array => [
                "{$partColor->part_id}-{$partColor->color_id}" => $partColor->bricqer_definition_id,
            ]);
    }

    /**
     * @param  list<array{item_type: string, item_id: string, color_id: string, stock: int, price: int, definition_id: string, title: string, definitions: array<int|string, int>}>  $rows
     * @param  Collection<covariant string, int>  $entityIds
     * @param  class-string<Part|Minifig>  $model
     */
    protected function applyDefinitionIdsFromInventory(array $rows, Collection $entityIds, string $itemType, string $model): int
    {
        /** @var array<string, string> $definitionsByBricklink */
        $definitionsByBricklink = [];

        foreach ($rows as $row) {
            if ($row['item_type'] !== $itemType || $row['definition_id'] === '') {
                continue;
            }

            $key = strtolower($row['item_id']);
            $definitionsByBricklink[$key] = $this->preferDefinitionId(
                $definitionsByBricklink[$key] ?? '',
                $row['definition_id'],
            );
        }

        return $this->bulkUpdateDefinitionIds($model, $entityIds, $definitionsByBricklink);
    }

    /**
     * @param  class-string<Part|Minifig>  $model
     * @param  Collection<covariant string, int>  $entityIdsByBricklink
     * @param  array<string, string>  $definitionsByBricklink
     */
    protected function bulkUpdateDefinitionIds(string $model, Collection $entityIdsByBricklink, array $definitionsByBricklink): int
    {
        if ($definitionsByBricklink === [] || $entityIdsByBricklink->isEmpty()) {
            return 0;
        }

        $candidatesById = [];

        foreach ($definitionsByBricklink as $bricklinkId => $definitionId) {
            $entityId = $entityIdsByBricklink->get($bricklinkId);

            if ($entityId === null || $definitionId === '') {
                continue;
            }

            $candidatesById[$entityId] = $this->preferDefinitionId(
                $candidatesById[$entityId] ?? '',
                $definitionId,
            );
        }

        if ($candidatesById === []) {
            return 0;
        }

        $entities = $model::query()
            ->whereIn('id', array_keys($candidatesById))
            ->get(['id', 'bricqer_definition_id'])
            ->keyBy('id');

        $toUpdate = [];

        foreach ($candidatesById as $entityId => $definitionId) {
            $entity = $entities->get($entityId);

            if ($entity === null) {
                continue;
            }

            $current = (string) ($entity->bricqer_definition_id ?? '');
            $best = $this->preferDefinitionId($current, $definitionId);

            if ($best !== '' && $best !== $current) {
                $toUpdate[$entityId] = $best;
            }
        }

        return BulkCaseUpdate::apply((new $model)->getTable(), 'id', 'bricqer_definition_id', $toUpdate);
    }

    /**
     * @param  list<string>  $bricklinkIds
     * @param  class-string<Part|Minifig>  $model
     * @return Collection<covariant string, int>
     */
    protected function mapBricklinkIds(array $bricklinkIds, string $model): Collection
    {
        $bricklinkIds = array_values(array_unique($bricklinkIds));
        $lowerIds = array_map(strtolower(...), $bricklinkIds);

        if ($lowerIds === []) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($lowerIds), '?'));

        return $model::query()
            ->whereNotNull('bricklink_id')
            ->whereRaw("LOWER(bricklink_id) IN ({$placeholders})", $lowerIds)
            ->get(['id', 'bricklink_id'])
            ->mapWithKeys(fn (Part|Minifig $entity): array => [strtolower((string) $entity->bricklink_id) => $entity->id]);
    }

    protected function zeroStockForMissingProducts(): int
    {
        // Require that we actually synced something before zeroing outsiders.
        if ($this->syncedProductIds === []) {
            return 0;
        }

        return Product::query()
            ->whereNotIn('id', array_unique($this->syncedProductIds))
            ->where(fn ($q) => $q->where('stock', '!=', 0)->orWhere('bricqer_stock', '!=', 0))
            ->update(['stock' => 0, 'bricqer_stock' => 0, 'source_definitions' => '[]']);
    }
}
