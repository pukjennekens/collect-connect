<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Page;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class SitemapController extends Controller
{
    private const PAGE_SIZE = 1000;

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap:index:'.sha1(url('/')), 300, function (): string {
            $xml = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach (['static', 'products', 'categories', 'sets', 'pages', 'articles'] as $type) {
                $count = $type === 'static' ? 1 : $this->query($type)->count();
                for ($page = 1; $page <= max(1, (int) ceil($count / self::PAGE_SIZE)); $page++) {
                    $xml .= '<sitemap><loc>'.e(route('sitemap.child', ['type' => $type, 'page' => $page])).'</loc></sitemap>';
                }
            }

            return $xml.'</sitemapindex>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function show(string $type, int $page): Response
    {
        abort_unless(in_array($type, ['static', 'products', 'categories', 'sets', 'pages', 'articles'], true) && $page > 0, 404);
        $xml = Cache::remember('sitemap:'.sha1(url('/')).':'.$type.':'.$page, 300, function () use ($type, $page): string {
            if ($type === 'static') {
                abort_unless($page === 1, 404);
                $urls = [url('/'), route('catalog.parts'), route('catalog.minifigs'), route('sets.index'), url('/blog')];
            } else {
                $records = $this->query($type)->orderBy('id')->forPage($page, self::PAGE_SIZE)->get();
                abort_if($page > 1 && $records->isEmpty(), 404);
                $urls = $records->map(fn ($record): string => match ($type) {
                    'products' => route('product.show', $record),
                    'categories' => route('catalog.parts', ['category_id' => $record->getKey()]),
                    'sets' => route('sets.show', $record),
                    'pages' => route('pages.show', $record),
                    'articles' => url('/blog/'.$record->getAttribute('slug')),
                });
            }
            $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($urls as $url) {
                $xml .= '<url><loc>'.e($url).'</loc></url>';
            }

            return $xml.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /** @return Builder<Article>|Builder<Page>|Builder<PartCategory>|Builder<Product>|Builder<Set> */
    private function query(string $type): Builder
    {
        return match ($type) {
            'products' => Product::query()->select('id'),
            'categories' => PartCategory::query()->select('id'),
            'sets' => Set::query()->select('id'),
            'pages' => Page::query()->published()->select('id', 'slug'),
            'articles' => Article::query()->published()->select('id', 'slug'),
            default => throw new InvalidArgumentException('Unknown sitemap type.'),
        };
    }
}
