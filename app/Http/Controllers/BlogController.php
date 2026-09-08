<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Support\Str;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        return inertia('blog/index', [
            'articles' => Article::query()
                ->published()
                ->latest('published_at')
                ->paginate(10),
        ]);
    }

    public function show(Article $article): Response
    {
        abort_unless(Article::query()->published()->whereKey($article->id)->exists(), 404);
        $article->content = Str::sanitizeHtml($article->content ?? '');

        return inertia('blog/article', [
            'article' => $article,
        ]);
    }
}
