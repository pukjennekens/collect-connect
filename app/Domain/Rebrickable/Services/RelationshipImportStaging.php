<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Stages one full relationship CSV before atomically replacing its published rows. */
class RelationshipImportStaging
{
    /** @param list<string> $columns */
    public function __construct(private string $table, private array $columns, private string $token) {}

    public function prepare(): void
    {
        // The dataset lock excludes another importer. Leftovers belong to killed jobs.
        DB::table($this->table.'_staging')->delete();
    }

    /** @param list<array<string, mixed>> $rows */
    public function append(array $rows): void
    {
        foreach ($rows as $row) {
            $quantity = filter_var($row['quantity'], FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 1) {
                throw new RuntimeException('Relationship quantities must be positive integers.');
            }
        }
        // A duplicate natural key fails the import rather than choosing arbitrary data.
        DB::table($this->table.'_staging')->insert(array_map(
            fn (array $row): array => [...$row, 'import_token' => $this->token],
            $rows,
        ));
    }

    public function publish(int $expectedRows): void
    {
        $staged = DB::table($this->table.'_staging')->where('import_token', $this->token);
        if ($expectedRows < 1 || $staged->count() !== $expectedRows) {
            throw new RuntimeException('Staged relationship count does not match the complete source.');
        }
        DB::transaction(function () use ($staged): void {
            DB::table($this->table)->delete();
            DB::table($this->table)->insertUsing(
                [...$this->columns, 'import_token', 'import_generation'],
                $staged->select([...$this->columns, 'import_token', 'import_token as import_generation']),
            );
        }, 3);
    }

    public function cleanup(): void
    {
        DB::table($this->table.'_staging')->where('import_token', $this->token)->delete();
    }
}
