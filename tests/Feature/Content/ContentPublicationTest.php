<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Models\Article;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContentPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_and_draft_content_is_hidden_everywhere(): void
    {
        foreach ([Page::class, Article::class] as $model) {
            $record = $model::query()->create(['title' => 'Future', 'slug' => 'future', 'content' => 'Hidden', 'is_published' => true, 'published_at' => now()->addDay()]);
            $this->get($model === Page::class ? route('pages.show', $record) : route('blog.show', $record))->assertNotFound();
        }
        $this->get(route('blog.index'))->assertInertia(fn ($page) => $page->has('articles.data', 0));
        foreach (['pages', 'articles'] as $type) {
            $this->get(route('sitemap.child', ['type' => $type, 'page' => 1]))->assertOk()->assertDontSee('future');
        }
    }

    public function test_rich_content_is_sanitized_without_changing_stored_content(): void
    {
        $article = Article::query()->create(['title' => 'Article', 'slug' => 'article', 'content' => '<p>Hello</p><script>alert(1)</script>', 'is_published' => true]);
        $this->get(route('blog.show', $article))->assertInertia(fn ($page) => $page->where('article.content', fn ($value) => str_contains($value, 'Hello') && ! str_contains($value, '<script>')));
        $this->assertStringContainsString('<script>', $article->fresh()->content);
    }

    public function test_sitemap_index_and_children_are_public_and_validate_types(): void
    {
        Cache::flush();
        $this->get(route('sitemap'))->assertOk()->assertSee('sitemapindex')->assertSee('/sitemaps/products/1.xml');
        $this->get(route('sitemap.child', ['type' => 'invalid', 'page' => 1]))->assertNotFound();
        $this->get(route('sitemap.child', ['type' => 'static', 'page' => 2]))->assertNotFound();
    }
}
