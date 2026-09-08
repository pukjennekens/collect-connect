<?php

declare(strict_types=1);

namespace App\Domain\Minifig\Jobs;

use App\Models\Minifig;
use App\Support\BulkCaseUpdate;
use Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ImportMinifigBricklinkNumbersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var int<1, max>
     */
    protected int $batchSize = 500;

    public function __construct(
        public ?string $filter = null,
        public ?string $file = null,
    ) {}

    /**
     * @return array{rows: int, updated: int, matched: int, skipped_empty: int, not_found: int}
     */
    public function handle(): array
    {
        $stats = [
            'rows' => 0,
            'updated' => 0,
            'matched' => 0,
            'skipped_empty' => 0,
            'not_found' => 0,
        ];

        $batch = [];

        foreach ($this->iterateRows() as $row) {
            $rebrickableId = trim((string) data_get($row, 'id', ''));
            $bricklinkId = trim((string) data_get($row, 'bricklink', ''));

            if ($rebrickableId === '') {
                $stats['skipped_empty']++;

                continue;
            }

            if ($this->filter !== null && ! str_contains($rebrickableId, $this->filter) && ! str_contains($bricklinkId, $this->filter)) {
                continue;
            }

            if ($bricklinkId === '') {
                $stats['skipped_empty']++;

                continue;
            }

            $stats['rows']++;
            $batch[$rebrickableId] = $bricklinkId;

            if (count($batch) >= $this->batchSize) {
                $this->mergeBatchStats($stats, $this->applyBatch($batch));
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->mergeBatchStats($stats, $this->applyBatch($batch));
        }

        return $stats;
    }

    /**
     * @param  array{rows: int, updated: int, matched: int, skipped_empty: int, not_found: int}  $stats
     * @param  array{updated: int, matched: int, not_found: int}  $batchStats
     */
    protected function mergeBatchStats(array &$stats, array $batchStats): void
    {
        $stats['updated'] += $batchStats['updated'];
        $stats['matched'] += $batchStats['matched'];
        $stats['not_found'] += $batchStats['not_found'];
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    protected function iterateRows(): Generator
    {
        if ($this->file !== null) {
            yield from $this->iterateCsvPath($this->file);

            return;
        }

        $contents = $this->downloadBricklinkData();
        $temporaryPath = $this->writeTemporaryCsv($contents);

        try {
            yield from $this->iterateCsvPath($temporaryPath);
        } finally {
            if (File::exists($temporaryPath)) {
                File::delete($temporaryPath);
            }
        }
    }

    protected function downloadBricklinkData(): string
    {
        $url = (string) config('minifig.bricklink_number_database_url');

        try {
            $response = Http::timeout(60)
                ->connectTimeout(10)
                ->get($url);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Failed to download minifig BrickLink CSV from [{$url}]: {$exception->getMessage()}. Pass --file=path/to/data.csv instead.",
                previous: $exception,
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to download minifig BrickLink CSV from [{$url}] (HTTP {$response->status()}). Pass --file=path/to/data.csv instead.",
            );
        }

        $body = $response->body();

        if ($body === '' || ! str_contains($body, 'bricklink')) {
            throw new RuntimeException(
                "Downloaded minifig BrickLink CSV from [{$url}] is empty or invalid. Pass --file=path/to/data.csv instead.",
            );
        }

        return $body;
    }

    protected function writeTemporaryCsv(string $contents): string
    {
        $path = storage_path('app/minifig-bricklink-'.uniqid('', true).'.csv');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return $path;
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    protected function iterateCsvPath(string $path): Generator
    {
        if (! File::exists($path)) {
            throw new RuntimeException("Minifig BrickLink CSV not found at [{$path}].");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open minifig BrickLink CSV at [{$path}].");
        }

        try {
            $header = fgetcsv($handle, escape: '\\');

            if ($header === false || $header === [null]) {
                throw new RuntimeException("Minifig BrickLink CSV at [{$path}] has no header row.");
            }

            // Strip UTF-8 BOM from the first header cell when present.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
            $header = array_map(
                static fn (?string $column): string => strtolower(trim((string) $column)),
                $header,
            );

            if (! in_array('id', $header, true) || ! in_array('bricklink', $header, true)) {
                throw new RuntimeException(
                    'Minifig BrickLink CSV must include "id" and "bricklink" columns. Found: '.implode(', ', $header),
                );
            }

            while (($row = fgetcsv($handle, escape: '\\')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                if (count($row) < count($header)) {
                    $row = array_pad($row, count($header), null);
                }

                /** @var array<string, string|null> $combined */
                $combined = array_combine($header, array_slice($row, 0, count($header)));

                yield $combined;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, string>  $batch  rebrickable_id => bricklink_id
     * @return array{updated: int, matched: int, not_found: int}
     */
    protected function applyBatch(array $batch): array
    {
        if ($batch === []) {
            return ['updated' => 0, 'matched' => 0, 'not_found' => 0];
        }

        $matchedIds = Minifig::query()
            ->whereIn('rebrickable_id', array_keys($batch))
            ->pluck('rebrickable_id')
            ->all();

        $matched = count($matchedIds);
        $notFound = count($batch) - $matched;

        if ($matched === 0) {
            return ['updated' => 0, 'matched' => 0, 'not_found' => $notFound];
        }

        $toUpdate = array_intersect_key($batch, array_flip($matchedIds));

        $updated = BulkCaseUpdate::apply(
            (new Minifig)->getTable(),
            'rebrickable_id',
            'bricklink_id',
            $toUpdate,
        );

        return [
            'updated' => $updated,
            'matched' => $matched,
            'not_found' => $notFound,
        ];
    }
}
