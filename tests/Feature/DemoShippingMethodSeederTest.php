<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Shipping\ShippingMethodResolver;
use App\Models\ShippingMethod;
use Database\Seeders\DemoShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DemoShippingMethodSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_methods_are_repeatable_and_resolve_country_and_weight_rates(): void
    {
        $this->seed(DemoShippingMethodSeeder::class);
        $this->seed(DemoShippingMethodSeeder::class);
        $this->assertSame(2, ShippingMethod::query()->count());
        $this->assertSame(0, ShippingMethod::query()->whereNotNull('bricqer_id')->count());
        $resolver = app(ShippingMethodResolver::class);
        $this->assertSame([395, 695], array_column($resolver->availableForCountry('NL', 100), 'price_cents'));
        $this->assertSame([695], array_column($resolver->availableForCountry('NL', 2001), 'price_cents'));
        $this->assertSame([995], array_column($resolver->availableForCountry('BE', 100), 'price_cents'));
    }

    public function test_demo_methods_are_unavailable_in_production(): void
    {
        $this->seed(DemoShippingMethodSeeder::class);
        $this->app->instance('env', 'production');
        $this->expectException(ValidationException::class);
        app(ShippingMethodResolver::class)->availableForCountry('NL', 100);
    }
}
