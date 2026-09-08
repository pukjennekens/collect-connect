<?php

declare(strict_types=1);

namespace App\Integrations\Bricqer\Requests\Lego\Report;

use App\Integrations\Bricqer\DataTransferObjects\UnconsolidatedInventory\InventoryItem;
use Generator;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

class GetUnconsolidatedInventoryRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/definitions/lego/report/unconsolidated-inventory';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'text/csv',
        ];
    }

    /**
     * Stream the response body instead of buffering it into memory.
     *
     * The report CSV is ~750MB / 200K rows, so loading the whole body into a
     * PHP string would exhaust memory. Streaming lets Saloon write the body to
     * a disk-backed stream which we then read row by row.
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'stream' => true,
            'timeout' => 1700,
        ];
    }

    /**
     * Lazily parse the CSV response into {@see InventoryItem} objects.
     *
     * The body is streamed to a temporary file in small chunks (never held in
     * memory as a single string) and the generator yields one item at a time,
     * so the full data set is never materialised at once. The delimiter is
     * detected from the header (Bricqer exports semicolon-separated CSV) and a
     * UTF-8 BOM is stripped if present.
     *
     * @return LazyCollection<int, InventoryItem>
     */
    public function createDtoFromResponse(Response $response): LazyCollection
    {
        return LazyCollection::make(function () use ($response): Generator {
            $stream = tmpfile();
            if (! $stream) {
                return;
            }

            try {
                $response->saveBodyToFile($stream, closeResource: false);

                $firstLine = fgets($stream);
                if ($firstLine === false) {
                    return;
                }

                $delimiter = $this->detectDelimiter($firstLine);
                rewind($stream);

                $header = fgetcsv($stream, separator: $delimiter, escape: '');
                if (! $header) {
                    return;
                }

                $header[0] = $this->stripBom((string) $header[0]);
                $header = array_values(array_filter($header, static fn (?string $column): bool => $column !== null && $column !== ''));
                $columnCount = count($header);

                $skipped = 0;

                while (($row = fgetcsv($stream, separator: $delimiter, escape: '')) !== false) {
                    if (count($row) < $columnCount) {
                        $skipped++;

                        continue;
                    }

                    $data = array_combine($header, array_slice($row, 0, $columnCount));

                    try {
                        yield InventoryItem::from($data);
                    } catch (Throwable) {
                        // Bricqer occasionally emits malformed rows (unescaped
                        // quotes shifting columns). Skip them rather than abort
                        // the whole import.
                        $skipped++;
                    }
                }

                if ($skipped > 0) {
                    throw new RuntimeException('Malformed Bricqer inventory rows; refusing partial stock synchronization.');
                }
            } finally {
                fclose($stream);
            }
        });
    }

    /**
     * Detect the CSV delimiter from the header line by picking the candidate
     * that appears most often.
     */
    protected function detectDelimiter(string $headerLine): string
    {
        $candidates = [';', ',', "\t", '|'];

        $counts = array_map(
            static fn (string $delimiter): int => substr_count($headerLine, $delimiter),
            $candidates,
        );

        $best = array_search(max($counts), $counts, strict: true);

        return $candidates[$best === false ? 1 : $best];
    }

    /**
     * Strip a leading UTF-8 byte order mark from the given value.
     */
    protected function stripBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF")
            ? substr($value, 3)
            : $value;
    }
}
