<?php

declare(strict_types=1);

namespace App\Domain\Part\Jobs;

use App\Domain\Bricqer\Services\BricqerDefinitionAttributeImporter;
use App\Domain\Minifig\Jobs\ImportMinifigImageJob;
use App\Integrations\Bricqer\DataTransferObjects\Definition\Definition;
use App\Integrations\Bricqer\Facades\Bricqer;
use App\Models\Minifig;
use App\Models\Pivots\PartColor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Single pass over Bricqer LEGO definitions:
 * - update part/minifig weight_grams + bricqer_definition_id
 * - queue missing part-color and minifig images from definition pictures
 */
class FullImportPartColorImagesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @return array{parts_updated: int, minifigs_updated: int, skipped: int, images_queued: int}
     */
    public function handle(): array
    {
        $attributes = new BricqerDefinitionAttributeImporter;
        $imagesQueued = 0;

        Bricqer::definition()->list()
            ->chunk(100)
            ->each(function (LazyCollection $definitions) use ($attributes, &$imagesQueued): void {
                foreach ($definitions as $definition) {
                    /** @var Definition $definition */
                    $attributes->ingest($definition);
                }

                $imagesQueued += $this->queuePartColorImages($definitions);
                $imagesQueued += $this->queueMinifigImages($definitions);
            });

        return [
            ...$attributes->flush(),
            'images_queued' => $imagesQueued,
        ];
    }

    /**
     * @param  LazyCollection<int, Definition>  $definitions
     */
    protected function queuePartColorImages(LazyCollection $definitions): int
    {
        $queued = 0;

        $partColors = PartColor::query()
            ->whereDoesntHave('media', fn ($query) => $query->where('collection_name', PartColor::BRICQER_IMAGE_COLLECTION))
            ->whereIn('bricqer_definition_id', $definitions->pluck('id')->map(fn ($id): string => (string) $id))
            ->get();

        $definitionsById = $definitions->keyBy(fn (Definition $definition): string => (string) $definition->id);

        $partColors->each(function (PartColor $partColor) use ($definitionsById, &$queued): void {
            $definition = $definitionsById->get((string) $partColor->bricqer_definition_id);

            if (! $definition || ! $definition->picture) {
                return;
            }

            // bricqer_image_url is set only after a successful download in ImportPartColorImageJob.
            ImportPartColorImageJob::dispatch($partColor->id, $definition->picture);
            $queued++;
        });

        return $queued;
    }

    /**
     * Minifigs have no color variants — match by BrickLink id and attach the
     * picture from the highest definition id in the chunk that has one.
     *
     * @param  LazyCollection<int, Definition>  $definitions
     */
    protected function queueMinifigImages(LazyCollection $definitions): int
    {
        /** @var Collection<string, Definition> $byBricklinkId */
        $byBricklinkId = $definitions
            ->filter(fn (Definition $definition): bool => $definition->legoType === 'M' && filled($definition->picture))
            ->collect()
            ->groupBy(fn (Definition $definition): string => strtolower($definition->legoId))
            ->map(fn (Collection $group): Definition => $group->sortByDesc('id')->firstOrFail());

        if ($byBricklinkId->isEmpty()) {
            return 0;
        }

        $keys = $byBricklinkId->keys()->map(fn ($key): string => (string) $key)->all();
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $minifigs = Minifig::query()
            ->whereDoesntHave('media', fn ($query) => $query->where('collection_name', Minifig::BRICQER_IMAGE_COLLECTION))
            ->whereNotNull('bricklink_id')
            ->whereRaw("LOWER(bricklink_id) IN ({$placeholders})", $keys)
            ->get();

        $queued = 0;

        foreach ($minifigs as $minifig) {
            $definition = $byBricklinkId->get(strtolower((string) $minifig->bricklink_id));

            if (! $definition || ! $definition->picture) {
                continue;
            }

            // bricqer_image_url is set only after a successful download in ImportMinifigImageJob.
            ImportMinifigImageJob::dispatch($minifig->id, $definition->picture);
            $queued++;
        }

        return $queued;
    }
}
