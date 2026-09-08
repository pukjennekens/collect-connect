<?php

declare(strict_types=1);

namespace App\Integrations\Bricqer\Requests\Commerce;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateOrderRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload) {}

    public function resolveEndpoint(): string
    {
        return '/shops/noshop/order/';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
