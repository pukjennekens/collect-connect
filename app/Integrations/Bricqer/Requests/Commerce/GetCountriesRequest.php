<?php

declare(strict_types=1);

namespace App\Integrations\Bricqer\Requests\Commerce;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCountriesRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/administration/country/';
    }
}
