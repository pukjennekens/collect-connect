<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('rebrickable_id')->unique();
            $table->string('name');
            $table->unsignedInteger('parent_id')->nullable()->index();
        });
        Schema::table('inventories', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
            $table->index(['set_id', 'version']);
        });
        foreach (['inventory_parts', 'inventory_minifigs', 'inventory_sets'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->uuid('import_token')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['inventory_parts', 'inventory_minifigs', 'inventory_sets'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['import_token']);
                $table->dropColumn('import_token');
            });
        }
        Schema::table('inventories', function (Blueprint $table): void {
            $table->index('set_id', 'inventories_set_id_rollback_index');
        });
        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropIndex(['set_id', 'version']);
            $table->dropColumn('version');
        });
        Schema::dropIfExists('themes');
    }
};
