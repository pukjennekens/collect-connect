<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Support\Str;
use Inertia\Response;

class PageController extends Controller
{
    public function show(Page $page): Response
    {
        abort_unless(Page::query()->published()->whereKey($page->id)->exists(), 404);
        $blocks = $page->blocks ?? [];
        array_walk_recursive($blocks, function (mixed &$value, string|int $key): void {
            if (in_array($key, ['html', 'content'], true) && is_string($value)) {
                $value = Str::sanitizeHtml($value);
            }
        });
        $page->blocks = $blocks;

        return inertia('pages/show', [
            'page' => $page,
        ]);
    }
}
