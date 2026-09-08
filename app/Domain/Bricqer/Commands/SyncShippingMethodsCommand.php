<?php

declare(strict_types=1);

namespace App\Domain\Bricqer\Commands;

use App\Integrations\Bricqer\BricqerConnector;
use App\Integrations\Bricqer\Requests\Commerce\GetCountriesRequest;
use App\Integrations\Bricqer\Requests\Commerce\GetShippingMethodsRequest;
use App\Models\ShippingMethod;
use App\Models\SyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SyncShippingMethodsCommand extends Command
{
    protected $signature = 'bricqer:sync-shipping-methods';

    protected $description = 'Import authoritative Bricqer country and weight based shipping rates.';

    public function handle(BricqerConnector $connector): int
    {
        $run = SyncRun::query()->create(['source' => 'bricqer_shipping', 'status' => 'running', 'started_at' => now()]);
        try {
            $countries = $connector->send(new GetCountriesRequest)->json();
            $methods = $connector->send(new GetShippingMethodsRequest)->json();
            Validator::make(['countries' => $countries, 'methods' => $methods], [
                'countries' => 'required|array|min:1', 'countries.*.id' => 'required|integer|min:1',
                'countries.*.country_code' => 'required|string|size:2', 'countries.*.shipping_code' => 'required|string',
                'methods' => 'present|array', 'methods.*.id' => 'required|integer|min:1', 'methods.*.costs' => 'present|array',
                'methods.*.costs.*.price' => 'required|numeric|min:0',
                'methods.*.costs.*.weight_min' => 'required|integer|min:0', 'methods.*.costs.*.weight_max' => 'required|integer|min:0',
                'methods.*.costs.*.shipping_code' => 'required|string',
            ])->validate();
            DB::transaction(function () use ($countries, $methods): void {
                $regions = collect($countries)->pluck('shipping_code', 'country_code')->all();
                $ids = collect($countries)->pluck('id', 'country_code')->all();
                foreach ($methods as $method) {
                    ShippingMethod::query()->updateOrCreate(['bricqer_id' => $method['id']], [
                        'name' => ($method['name'] ?? null) ?: ($method['description'] ?? 'Shipping'),
                        'rate_bands' => $method['costs'], 'country_regions' => $regions, 'country_ids' => $ids,
                        'track_trace' => $method['track_trace'] ?? false, 'is_active' => $method['costs'] !== [],
                        'countries' => array_keys($regions),
                    ]);
                }
                ShippingMethod::query()->whereNotIn('bricqer_id', array_column($methods, 'id'))->orWhereNull('bricqer_id')->update(['is_active' => false]);
            });
            $run->update(['status' => 'succeeded', 'finished_at' => now(), 'stats' => ['imported' => count($methods)]]);
            $this->info('Imported '.count($methods).' shipping methods.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
