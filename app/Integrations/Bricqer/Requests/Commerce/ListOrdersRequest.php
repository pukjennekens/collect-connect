<?php

declare(strict_types=1);

namespace App\Integrations\Bricqer\Requests\Commerce;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListOrdersRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public int $page = 1, public ?string $modifiedSince = null) {}

    public function resolveEndpoint(): string
    {
        return '/orders/order/';
    }

    protected function defaultQuery(): array
    {
        return array_filter(['page' => $this->page, 'page_size' => 100, 'modified__gte' => $this->modifiedSince], fn ($value) => $value !== null);
    }
}
