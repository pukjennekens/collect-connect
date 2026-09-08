<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class DemoShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            return;
        }

        foreach ([
            ['code' => 'demo-postnl-letterbox', 'name' => 'PostNL Brievenbuspakje (test)', 'maximum' => 2000, 'rates' => ['NL' => '3.95']],
            ['code' => 'demo-postnl-parcel', 'name' => 'PostNL Pakket (test)', 'maximum' => 23000, 'rates' => ['NL' => '6.95', 'BE' => '9.95']],
        ] as $option) {
            $bands = [];
            foreach ($option['rates'] as $country => $price) {
                $bands[] = ['shipping_code' => $country, 'weight_min' => 0, 'weight_max' => $option['maximum'], 'price' => $price];
            }

            ShippingMethod::query()->updateOrCreate(['code' => $option['code'], 'bricqer_id' => null], [
                'name' => $option['name'],
                'area' => 'Demo',
                'price_cents' => (int) round((float) $option['rates']['NL'] * 100),
                'track_trace' => true,
                'countries' => array_keys($option['rates']),
                'country_regions' => array_combine(array_keys($option['rates']), array_keys($option['rates'])),
                'country_ids' => [],
                'rate_bands' => $bands,
                'is_active' => true,
            ]);
        }
    }
}
