<?php

declare(strict_types=1);

namespace App\Integrations\Bricqer;

use InvalidArgumentException;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class BricqerConnector extends Connector
{
    use AlwaysThrowOnErrors;

    public function __construct(
        protected string $domain,
        protected string $apiKey,
    ) {}

    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 10, 'timeout' => 60];
    }

    public function resolveBaseUrl(): string
    {
        if (! preg_match('/^[a-z0-9]+\.bricqer\.com$/', $this->domain)) {
            throw new InvalidArgumentException('The domain must be in the format xxxx.bricqer.com');
        }

        return "https://{$this->domain}/api/v1";
    }

    /**
     * Bricqer authenticates with the "Api-Key" Authorization scheme.
     *
     * @see https://www.bricqer.com/guides/using-the-api
     *
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => "Api-Key {$this->apiKey}",
        ];
    }
}
