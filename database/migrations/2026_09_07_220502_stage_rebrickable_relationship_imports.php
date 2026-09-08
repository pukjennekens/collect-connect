<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $keys = [
        'inventory_parts' => ['inventory_id', 'part_id', 'color_id', 'is_spare'],
        'inventory_minifigs' => ['inventory_id', 'minifig_id'],
        'inventory_sets' => ['inventory_id', 'set_id'],
    ];

    public function up(): void
    {
        foreach ($this->keys as $table => $keys) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $keys): void {
                // Existing unvalidated rows remain nullable until a complete repair.
                $blueprint->uuid('import_generation')->nullable();
                $blueprint->unique(['import_generation', ...$keys], $table.'_generation_unique');
            });
            Schema::create($table.'_staging', function (Blueprint $blueprint) use ($table, $keys): void {
                $blueprint->uuid('import_token');
                foreach ($keys as $key) {
                    if ($key === 'is_spare') {
                        $blueprint->boolean($key);
                    } else {
                        $blueprint->unsignedBigInteger($key);
                    }
                }
                $blueprint->unsignedInteger('quantity');
                $blueprint->unique(['import_token', ...$keys], $table.'_staging_unique');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->keys as $table => $keys) {
            Schema::dropIfExists($table.'_staging');
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropUnique($table.'_generation_unique');
                $blueprint->dropColumn('import_generation');
            });
        }
    }
};
