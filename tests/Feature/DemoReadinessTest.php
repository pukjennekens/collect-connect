<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Color;
use App\Models\Inventory;
use App\Models\Page;
use App\Models\Part;
use App\Models\Set;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_content_is_published_idempotently_and_preserves_editorial_changes(): void
    {
        $this->artisan('demo:prepare-content')->assertSuccessful();
        Page::query()->where('slug', 'contact')->update(['title' => 'Editorial contact']);
        $this->artisan('demo:prepare-content')->assertSuccessful();
        $this->assertSame(4, Page::query()->count());
        $this->assertSame(2, Article::query()->count());
        $this->assertSame('Editorial contact', Page::query()->where('slug', 'contact')->value('title'));
        foreach (Page::all() as $page) {
            $this->get(route('pages.show', $page))->assertOk();
        }
        $this->get(route('blog.index'))->assertInertia(fn ($page) => $page->has('articles.data', 2));
    }

    public function test_article_resource_serializes_a_single_article(): void
    {
        $article = Article::factory()->create(['title' => 'Demo article']);
        $this->assertSame('Demo article', $article->toResource()->resolve()['title']);
    }

    public function test_search_exposes_all_color_choices(): void
    {
        Color::factory()->count(81)->sequence(fn ($sequence) => ['bricklink_color_id' => (string) ($sequence->index + 1)])->create();
        $this->get(route('catalog.search'))->assertOk()->assertInertia(fn ($page) => $page->has('filters.colors', 81));
    }

    public function test_missing_public_page_has_branded_recovery_and_api_remains_json(): void
    {
        $this->get('/missing-demo-page')->assertNotFound()->assertInertia(fn ($page) => $page->component('error')->where('status', 404));
        $this->getJson('/api/missing-demo-page')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_set_parts_are_paginated_separately_from_minifigures(): void
    {
        $set = Set::factory()->create();
        $inventory = Inventory::factory()->create(['set_id' => $set->id]);
        $color = Color::factory()->create();
        foreach (Part::factory()->count(49)->create() as $part) {
            DB::table('inventory_parts')->insert(['inventory_id' => $inventory->id, 'part_id' => $part->id, 'color_id' => $color->id, 'quantity' => 1, 'is_spare' => false]);
        }
        $this->get(route('sets.show', $set))->assertOk()->assertInertia(fn ($page) => $page->has('parts.data', 48)->where('parts.total', 49)->has('minifigs.data', 0));
        $this->get(route('sets.show', [$set, 'parts_page' => 2]))->assertOk()->assertInertia(fn ($page) => $page->has('parts.data', 1)->where('parts.current_page', 2));
    }
}
