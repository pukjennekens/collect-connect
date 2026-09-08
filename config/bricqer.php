<?php

declare(strict_types=1);

return [
    'domain' => env('BRICQER_DOMAIN'),
    'orders_enabled' => (bool) env('BRICQER_ORDERS_ENABLED', false),
    'marketplace' => env('BRICQER_MARKETPLACE', 'Collect2Connect-staging'),
    'api_key' => env('BRICQER_API_KEY'),
];
