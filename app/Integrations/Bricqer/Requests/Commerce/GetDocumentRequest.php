<?php

declare(strict_types=1);

namespace App\Integrations\Bricqer\Requests\Commerce;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetDocumentRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public int $documentId) {}

    public function resolveEndpoint(): string
    {
        return "/administration/document/{$this->documentId}/download/";
    }
}
