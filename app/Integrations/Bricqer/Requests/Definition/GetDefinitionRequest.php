<?php

declare(strict_types=1);

namespace App\Integrations\Bricqer\Requests\Definition;

use App\Integrations\Bricqer\DataTransferObjects\Definition\Definition;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetDefinitionRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $definitionId) {}

    public function resolveEndpoint(): string
    {
        return "/definitions/lego/definition/{$this->definitionId}";
    }

    public function createDtoFromResponse(Response $response): Definition
    {
        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        // Single-definition responses omit `id` (it is only in the URL) and use
        // `remainingQuantity` instead of the list endpoint's `totalRemainingQuantity`.
        $payload['id'] = (int) ($payload['id'] ?? $this->definitionId);

        if (! array_key_exists('totalRemainingQuantity', $payload)) {
            $remaining = $payload['remainingQuantity'] ?? 0;
            $payload['totalRemainingQuantity'] = is_numeric($remaining) ? (int) $remaining : 0;
        }

        return Definition::from($payload);
    }
}
