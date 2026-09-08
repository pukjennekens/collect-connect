<?php

declare(strict_types=1);

namespace App\Domain\Minifig\Commands;

use App\Domain\Minifig\Jobs\ImportMinifigImageFromDefinitionJob;
use App\Models\Minifig;
use App\Models\Product;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\LazyCollection;

#[Signature('minifig:import-images
    {--definition= : Only import for minifigs with this Bricqer definition id}
    {--only-products : Only minifigs that have a shop product}
    {--limit= : Max number of minifigs to queue}
    {--sync : Run imports immediately instead of queueing}')]
class ImportMinifigImagesCommand extends Command
{
    protected $description = 'Queue (or sync) Bricqer image imports for minifigs missing media — uses definition picture when available, otherwise the BrickLink CDN URL';

    public function handle(): int
    {
        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? max(1, (int) $limit) : null;
        $sync = (bool) $this->option('sync');

        $queued = 0;

        $this->retrieveEligibleMinifigs(
            definitionId: is_string($this->option('definition')) && $this->option('definition') !== ''
                ? $this->option('definition')
                : null,
            onlyProducts: (bool) $this->option('only-products'),
            limit: $limit,
        )->each(function (Minifig $minifig) use (&$queued, $sync): void {
            if ($sync) {
                ImportMinifigImageFromDefinitionJob::dispatchSync($minifig->id);
            } else {
                ImportMinifigImageFromDefinitionJob::dispatch($minifig->id);
            }
            $queued++;
        });

        $this->info(($sync ? 'Imported' : 'Queued')." {$queued} minifig image imports.");

        return self::SUCCESS;
    }

    /**
     * Minifigs that still need a Bricqer image: have a BrickLink id (CDN fallback)
     * or a definition id, and no image URL recorded yet.
     *
     * @return LazyCollection<int, Minifig>
     */
    protected function retrieveEligibleMinifigs(
        ?string $definitionId = null,
        bool $onlyProducts = false,
        ?int $limit = null,
    ): LazyCollection {
        // Collect ids first so --limit works (lazyById ignores query limit).
        // Eligibility is based on missing media, not bricqer_image_url, so failed
        // downloads can be retried.
        $query = Minifig::query()
            ->whereDoesntHave('media', function ($query): void {
                $query->where('collection_name', Minifig::BRICQER_IMAGE_COLLECTION);
            })
            ->where(function ($query) use ($definitionId): void {
                if ($definitionId !== null) {
                    $query->where('bricqer_definition_id', $definitionId);

                    return;
                }

                $query
                    ->whereNotNull('bricklink_id')
                    ->where('bricklink_id', '!=', '');
            })
            ->when($onlyProducts, function ($query): void {
                $morph = (new Minifig)->getMorphClass();
                $query->whereIn('id', Product::query()
                    ->where('productable_type', $morph)
                    ->select('productable_id'));
            })
            ->orderBy('id');
        if ($limit !== null) {
            $query->limit($limit);
        }
        $ids = $query->pluck('id');

        return LazyCollection::make(function () use ($ids) {
            foreach ($ids->chunk(100) as $chunk) {
                yield from Minifig::query()
                    ->whereIn('id', $chunk->all())
                    ->orderBy('id')
                    ->get();
            }
        });
    }
}
