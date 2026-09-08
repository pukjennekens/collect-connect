<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Rebrickable\Contracts\RebrickableDownloader;
use App\Domain\Rebrickable\Jobs\ImportRebrickableEntityJob;
use App\Domain\Rebrickable\Services\Imports\ColorImportService;
use App\Domain\Rebrickable\Services\Imports\InventoryImportService;
use App\Domain\Rebrickable\Services\Imports\InventoryMinifigImportService;
use App\Domain\Rebrickable\Services\Imports\InventoryPartImportService;
use App\Domain\Rebrickable\Services\Imports\InventorySetImportService;
use App\Domain\Rebrickable\Services\Imports\MinifigImportService;
use App\Domain\Rebrickable\Services\Imports\PartCategoryImportService;
use App\Domain\Rebrickable\Services\Imports\PartImportService;
use App\Domain\Rebrickable\Services\Imports\SetImportService;
use App\Domain\Rebrickable\Services\Imports\ThemeImportService;
use App\Models\Color;
use App\Models\Inventory;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Set;
use App\Models\Theme;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class RebrickableImportTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $rows, bool $fail = false): void
    {
        $this->app->instance(RebrickableDownloader::class, new class($rows, $fail) implements RebrickableDownloader
        {
            public function __construct(private array $rows, private bool $fail) {}

            public function retrieveRebrickableDataFromUrl(string $url): Generator
            {
                foreach ($this->rows as $row) {
                    yield $row;
                }
                if ($this->fail) {
                    throw new RuntimeException('Download interrupted');
                }
            }
        });
    }

    public function test_full_import_is_chained_in_dependency_order(): void
    {
        Bus::fake();
        $this->artisan('rebrickable:import-entity')->assertSuccessful();
        Bus::assertChained(array_map(fn (string $service) => $service === ThemeImportService::class ? (new ImportRebrickableEntityJob($service))->onQueue('imports') : new ImportRebrickableEntityJob($service), [ThemeImportService::class, PartCategoryImportService::class, ColorImportService::class, PartImportService::class, MinifigImportService::class, SetImportService::class, InventoryImportService::class, InventoryPartImportService::class, InventoryMinifigImportService::class, InventorySetImportService::class]));
        $this->artisan('rebrickable:import-entity', ['--entity' => 'invalid'])->assertFailed();
    }

    public function test_themes_parts_and_inventory_versions_import_with_external_ids(): void
    {
        $this->data([['id' => 40, 'name' => 'Parent', 'parent_id' => ''], ['id' => 41, 'name' => 'Child', 'parent_id' => 40]]);
        app(ThemeImportService::class)->import();
        $this->assertSame('Parent', Theme::where('rebrickable_id', 41)->first()->parent->name);
        $category = PartCategory::factory()->create(['rebrickable_id' => '999']);
        $this->data([['part_num' => "abc'1", 'name' => 'Part', 'part_cat_id' => '999']]);
        app(PartImportService::class)->import();
        $part = Part::where('rebrickable_id', "abc'1")->first();
        $this->assertSame($category->id, $part->part_category_id);
        app(PartImportService::class)->import();
        $this->assertSame($part->id, Part::sole()->id);
        $set = Set::factory()->create(['set_num' => '123-1']);
        $this->data([['id' => 9999, 'set_num' => '123-1', 'version' => 2]]);
        app(InventoryImportService::class)->import();
        $this->assertSame(2, $set->latestInventory->version);
    }

    public function test_relationship_refresh_resolves_ids_and_removes_only_successfully_replaced_rows(): void
    {
        $inventory = Inventory::factory()->create(['rebrickable_id' => '777']);
        $part = Part::factory()->create(['rebrickable_id' => '3001']);
        $color = Color::factory()->create(['rebrickable_id' => '99']);
        $row = ['inventory_id' => '777', 'part_num' => '3001', 'color_id' => '99', 'quantity' => 4, 'is_spare' => 'False'];
        $this->data([$row]);
        app(InventoryPartImportService::class)->import();
        $this->assertDatabaseHas('inventory_parts', ['inventory_id' => $inventory->id, 'part_id' => $part->id, 'color_id' => $color->id, 'quantity' => 4]);
        $this->data([[...$row, 'is_spare' => 'True', 'quantity' => 1]], true);
        try {
            app(InventoryPartImportService::class)->import();
        } catch (RuntimeException) {
        }
        $this->assertDatabaseCount('inventory_parts', 1);
        $this->data([[...$row, 'is_spare' => 'True', 'quantity' => 1]]);
        app(InventoryPartImportService::class)->import();
        $this->assertDatabaseCount('inventory_parts', 1);
        $this->assertDatabaseHas('inventory_parts', ['is_spare' => true, 'quantity' => 1]);
    }

    public function test_large_dataset_is_batched_and_existing_ids_are_stable(): void
    {
        $rows = array_map(fn (int $id): array => ['id' => $id, 'name' => "Category {$id}"], range(1, 1001));
        $this->data($rows);
        app(PartCategoryImportService::class)->import();
        $id = PartCategory::where('rebrickable_id', 501)->value('id');
        $this->data($rows);
        app(PartCategoryImportService::class)->import();
        $this->assertDatabaseCount('part_categories', 1001);
        $this->assertSame($id, PartCategory::where('rebrickable_id', 501)->value('id'));
    }

    public function test_zip_download_streams_csv_and_rejects_malformed_rows(): void
    {
        $file = tmpfile();
        $path = stream_get_meta_data($file)['uri'];
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('parts.csv', "id,name\n1,Brick\n");
        $zip->close();
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(file_get_contents($path))]);
        $downloader = new \App\Domain\Rebrickable\Services\RebrickableDownloadService;
        $this->assertSame([['id' => '1', 'name' => 'Brick']], iterator_to_array($downloader->retrieveRebrickableDataFromUrl('https://example.test/parts.zip')));
        fclose($file);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('invalid zip')]);
        $this->expectException(RuntimeException::class);
        iterator_to_array($downloader->retrieveRebrickableDataFromUrl('https://example.test/parts.zip'));
    }

    public function test_remaining_datasets_and_relationships_import_and_update(): void
    {
        $this->data([['id' => 5, 'name' => 'Blue', 'rgb' => '0000FF', 'is_trans' => 'True']]);
        app(ColorImportService::class)->import();
        $this->assertTrue(Color::sole()->is_transparent);
        $this->data([['fig_num' => 'fig-1', 'name' => 'Minifigure']]);
        app(MinifigImportService::class)->import();
        $this->data([['set_num' => 'set-1', 'name' => 'Set', 'year' => 2020, 'theme_id' => 1, 'num_parts' => 20, 'img_url' => 'https://example.test/set.png']]);
        app(SetImportService::class)->import();
        $set = Set::sole();
        $inventory = Inventory::factory()->create(['rebrickable_id' => '123', 'set_id' => $set->id]);
        $this->data([['inventory_id' => '123', 'fig_num' => 'fig-1', 'quantity' => 2]]);
        app(InventoryMinifigImportService::class)->import();
        $this->assertSame('Minifigure', $inventory->minifigs->sole()->name);
        $this->data([['inventory_id' => '123', 'set_num' => 'set-1', 'quantity' => 3]]);
        app(InventorySetImportService::class)->import();
        app(InventorySetImportService::class)->import();
        $this->assertDatabaseCount('inventory_sets', 1);
        $this->assertDatabaseHas('inventory_sets', ['inventory_id' => $inventory->id, 'set_id' => $set->id, 'quantity' => 3]);
    }

    public function test_empty_and_unknown_relationship_data_fail_without_deleting_existing_rows(): void
    {
        $this->data([]);
        try {
            app(InventoryPartImportService::class)->import();
            $this->fail('Empty dataset accepted');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('empty', $exception->getMessage());
        }
        $this->data([['inventory_id' => 'missing', 'part_num' => 'missing', 'color_id' => 'missing', 'quantity' => 1, 'is_spare' => 'False']]);
        $this->expectException(RuntimeException::class);
        app(InventoryPartImportService::class)->import();
    }

    public function test_failure_after_a_full_batch_never_publishes_partial_relationships(): void
    {
        $inventory = Inventory::factory()->create(['rebrickable_id' => 'source-inventory']);
        $color = Color::factory()->create(['rebrickable_id' => 'source-color']);
        $category = PartCategory::factory()->create();
        $parts = array_map(fn (int $id): array => ['rebrickable_id' => 'source-part-'.$id, 'name' => 'Part '.$id, 'part_category_id' => $category->id], range(1, 501));
        Part::query()->insert($parts);
        $firstPart = Part::where('rebrickable_id', 'source-part-1')->firstOrFail();
        $original = ['inventory_id' => $inventory->id, 'part_id' => $firstPart->id, 'color_id' => $color->id, 'quantity' => 99, 'is_spare' => false];
        \Illuminate\Support\Facades\DB::table('inventory_parts')->insert([$original, $original]);
        $rows = array_map(fn (array $part): array => ['inventory_id' => 'source-inventory', 'part_num' => $part['rebrickable_id'], 'color_id' => 'source-color', 'quantity' => 1, 'is_spare' => 'False'], $parts);
        $this->data($rows, true);
        try {
            app(InventoryPartImportService::class)->import();
            $this->fail('Interrupted CSV accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Download interrupted', $exception->getMessage());
        }
        $this->assertDatabaseCount('inventory_parts', 2);
        $this->assertDatabaseHas('inventory_parts', $original);
        $this->assertDatabaseCount('inventory_parts_staging', 0);

        $this->data($rows);
        app(InventoryPartImportService::class)->import();
        $this->assertDatabaseCount('inventory_parts', 501);
        $this->assertDatabaseMissing('inventory_parts', ['quantity' => 99]);
        $this->assertSame($firstPart->id, Part::where('rebrickable_id', 'source-part-1')->value('id'));
        $this->assertDatabaseHas('inventory_parts', [...$original, 'quantity' => 1]);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('inventory_parts')->whereNull('import_generation')->count());
        $this->assertDatabaseCount('inventory_parts_staging', 0);

        $this->data([...$rows, $rows[0]]);
        try {
            app(InventoryPartImportService::class)->import();
            $this->fail('Duplicate relationship accepted.');
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            $this->assertDatabaseCount('inventory_parts', 501);
        }
        $this->assertDatabaseCount('inventory_parts_staging', 0);
    }

    public function test_publication_failure_rolls_back_deleted_legacy_rows(): void
    {
        $inventory = Inventory::factory()->create();
        $old = Part::factory()->create();
        $new = Part::factory()->create();
        $color = Color::factory()->create();
        $original = ['inventory_id' => $inventory->id, 'part_id' => $old->id, 'color_id' => $color->id, 'quantity' => 9, 'is_spare' => false];
        \Illuminate\Support\Facades\DB::table('inventory_parts')->insert($original);
        $staging = new \App\Domain\Rebrickable\Services\RelationshipImportStaging('inventory_parts', array_keys($original), (string) \Illuminate\Support\Str::uuid());
        $staging->prepare();
        $staging->append([[...$original, 'part_id' => $new->id]]);
        $new->delete();
        try {
            $staging->publish(1);
            $this->fail('Missing target relationship published.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertDatabaseCount('inventory_parts', 1);
            $this->assertDatabaseHas('inventory_parts', $original);
        } finally {
            $staging->cleanup();
        }
    }

    public function test_foreground_command_dispatches_synchronously(): void
    {
        Bus::fake();
        $this->artisan('rebrickable:import-entity', ['--entity' => 'themes', '--sync' => true])->assertSuccessful();
        Bus::assertDispatched(ImportRebrickableEntityJob::class, fn ($job): bool => $job->importService === ThemeImportService::class);
    }

    public function test_foreground_import_runs_even_when_queue_overlap_lock_is_held(): void
    {
        $this->data([['id' => 42, 'name' => 'Foreground theme', 'parent_id' => '']]);
        $job = new ImportRebrickableEntityJob(ThemeImportService::class);
        $middleware = $job->middleware()[0];
        $lock = \Illuminate\Support\Facades\Cache::lock($middleware->getLockKey($job), 1900);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('rebrickable:import-entity', ['--entity' => 'themes', '--sync' => true])->assertSuccessful();
            $this->assertDatabaseHas('themes', ['rebrickable_id' => 42, 'name' => 'Foreground theme']);
            $this->assertDatabaseHas('sync_runs', ['source' => 'rebrickable', 'status' => 'success']);
        } finally {
            $lock->release();
        }
    }
}
