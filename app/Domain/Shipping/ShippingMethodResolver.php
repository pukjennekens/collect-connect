<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Models\ShippingMethod;
use Illuminate\Validation\ValidationException;

class ShippingMethodResolver
{
    /** @return list<array{id:int, name:string, price_cents:int, code:string, track_trace:bool}> */
    public function availableForCountry(string $country, ?float $weightGrams = null): array
    {
        $country = strtoupper($country);
        if ($weightGrams === null || $weightGrams < 0) {
            throw ValidationException::withMessages(['shipping_method_id' => 'Het gewicht van een artikel ontbreekt. Neem contact op voor verzending.']);
        }
        $methods = [];
        $query = ShippingMethod::query()->where('is_active', true)->where(function ($query): void {
            $query->whereNotNull('bricqer_id');
            if (app()->environment(['local', 'testing', 'staging'])) {
                $query->orWhereIn('code', ['demo-postnl-letterbox', 'demo-postnl-parcel']);
            }
        });
        foreach ($query->get() as $method) {
            $region = ($method->country_regions ?? [])[$country] ?? null;
            if ($region === null) {
                continue;
            }
            $rates = collect($method->rate_bands ?? [])->filter(fn (array $rate): bool => $rate['shipping_code'] === $region && $weightGrams >= $rate['weight_min'] && $weightGrams <= $rate['weight_max']);
            if ($rates->count() !== 1) {
                continue;
            }
            $methods[] = ['id' => $method->id, 'name' => $method->name, 'price_cents' => (int) round((float) $rates->first()['price'] * 100), 'code' => (string) $region, 'track_trace' => $method->track_trace];
        }
        if ($methods === []) {
            throw ValidationException::withMessages(['shipping_method_id' => 'Er is geen geldige verzendmethode voor dit land en gewicht.']);
        }

        return array_values(collect($methods)->sortBy('price_cents')->all());
    }

    /** @return array{id:int, name:string, price_cents:int, code:string, track_trace:bool} */
    public function resolve(int $shippingMethodId, string $country, ?float $weightGrams = null): array
    {
        foreach ($this->availableForCountry($country, $weightGrams) as $method) {
            if ($method['id'] === $shippingMethodId) {
                return $method;
            }
        }
        throw ValidationException::withMessages(['shipping_method_id' => 'Kies een geldige verzendmethode voor dit land.']);
    }
}
