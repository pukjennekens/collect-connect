<?php

declare(strict_types=1);

namespace App\Http\Resources\Set;

use App\Models\Set;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Set
 */
class SetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'set_num' => $this->set_num,
            'url' => route('sets.show', $this->resource),
            'name' => $this->name,
            'year' => $this->year,
            'num_parts' => $this->num_parts,
            'image' => MediaUrl::fromMedia(
                $this->getFirstMedia(Set::IMAGE_COLLECTION),
                [Set::LARGE_CONVERSION, Set::THUMB_CONVERSION],
            ) ?? $this->img_url,
        ];
    }
}
