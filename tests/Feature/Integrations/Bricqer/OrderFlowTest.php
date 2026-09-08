<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations\Bricqer;

use App\Domain\Bricqer\Services\BricqerOrderService;
use App\Integrations\Bricqer\Requests\Commerce\CreateContactRequest;
use App\Integrations\Bricqer\Requests\Commerce\CreateOrderRequest;
use App\Integrations\Bricqer\Requests\Commerce\GetCountriesRequest;
use App\Integrations\Bricqer\Requests\Commerce\GetDocumentRequest;
use App\Integrations\Bricqer\Requests\Commerce\GetOrderRequest;
use App\Integrations\Bricqer\Requests\Commerce\GetShippingMethodsRequest;
use App\Integrations\Bricqer\Requests\Commerce\ListOrdersRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bricqer.domain' => 'test.bricqer.com', 'bricqer.api_key' => 'fake', 'bricqer.orders_enabled' => true]);
    }

    public function test_export_uses_exact_sources_and_repeated_export_does_not_create_duplicates(): void
    {
        $mock = Saloon::fake([
            CreateContactRequest::class => MockResponse::make(['id' => 55]),
            CreateOrderRequest::class => MockResponse::make(['meta_id' => '99']),
            GetOrderRequest::class => MockResponse::make(['id' => 99, 'status' => 'PAID']),
        ]);
        $order = $this->order();
        $service = app(BricqerOrderService::class);
        $service->export($order);
        $service->export($order->refresh());
        $mock->assertSentCount(3);
        $mock->assertSent(function (\Saloon\Http\Request $request): bool {
            if (! $request instanceof CreateOrderRequest) {
                return false;
            }
            $body = $request->body()->all();

            return $body['contact'] === 55 && $body['send_invoice_email'] === false && $body['paid'] === true
                && $body['batches'][0]['items'][0]['definition_id'] === 123
                && $body['batches'][0]['items'][0]['price'] === '2.50'
                && $body['invoice_lines'][0]['amount'] === '3.95';
        });
        $this->assertSame('99', $order->refresh()->bricqer_order_id);
        $this->assertSame('synced', $order->sync_status);
    }

    public function test_ambiguous_submission_reconciles_instead_of_posting_again(): void
    {
        $order = $this->order();
        $order->forceFill(['sync_status' => 'ambiguous'])->save();
        $mock = Saloon::fake([
            ListOrdersRequest::class => MockResponse::make(['results' => [['id' => 77]], 'next' => null]),
            GetOrderRequest::class => MockResponse::make(['id' => 77, 'status' => 'PAID', 'order' => json_encode([
                'custom_marketplace_order_id' => $order->number, 'custom_marketplace' => config('bricqer.marketplace'),
            ])]),
        ]);
        app(BricqerOrderService::class)->export($order);
        $this->assertSame('77', $order->refresh()->bricqer_order_id);
        $mock->assertNotSent(CreateOrderRequest::class);
    }

    public function test_unknown_creation_result_never_blindly_reposts(): void
    {
        $order = $this->order();
        $order->forceFill(['sync_status' => 'ambiguous'])->save();
        $mock = Saloon::fake([ListOrdersRequest::class => MockResponse::make(['results' => [], 'next' => null])]);
        app(BricqerOrderService::class)->export($order);
        $this->assertSame('ambiguous', $order->refresh()->sync_status);
        $mock->assertNotSent(CreateOrderRequest::class);
    }

    public function test_separate_billing_blocks_unsupported_remote_mapping_before_any_write(): void
    {
        $order = $this->order();
        $order->forceFill(['billing_address' => [...$order->billing_address, 'city' => 'Rotterdam']])->save();
        $mock = Saloon::fake([]);
        try {
            app(BricqerOrderService::class)->export($order);
            $this->fail('Expected unsupported mapping exception.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Separate billing', $exception->getMessage());
        }
        $mock->assertNothingSent();
    }

    public function test_status_tracking_and_invoice_are_exposed_without_changing_payment(): void
    {
        $order = $this->order();
        $order->forceFill(['bricqer_order_id' => '99'])->save();
        Saloon::fake([GetOrderRequest::class => MockResponse::make([
            'id' => 99, 'status' => 'SHIPPED', 'shipment' => ['tracking_code' => 'TRACK', 'track_trace_url' => 'https://example.org/track', 'provider_id' => 'postnl'],
            'invoice_set' => [['invoice_type' => 'standard', 'document' => 88]],
        ])]);
        app(BricqerOrderService::class)->sync($order);
        $this->assertSame('shipped', $order->refresh()->fulfilment_status);
        $this->assertSame('paid', $order->status);
        $this->assertSame('TRACK', $order->tracking_code);
        $this->assertSame(88, $order->invoice_document_id);
    }

    public function test_invoice_download_requires_owner_and_real_document(): void
    {
        $order = $this->order();
        $owner = User::factory()->create();
        $order->forceFill(['user_id' => $owner->id, 'invoice_document_id' => 88])->save();
        Saloon::fake([GetDocumentRequest::class => MockResponse::make('%PDF-test')]);
        $this->actingAs(User::factory()->create())->get(route('account.orders.invoice', $order))->assertForbidden();
        $this->actingAs($owner)->get(route('account.orders.invoice', $order))->assertOk()->assertSee('%PDF-test');
    }

    public function test_shipping_import_keeps_all_bands_and_old_rates_on_failure(): void
    {
        Saloon::fake([
            GetCountriesRequest::class => MockResponse::make([['id' => 1, 'country_code' => 'NL', 'shipping_code' => 'NL']]),
            GetShippingMethodsRequest::class => MockResponse::make([['id' => 3, 'name' => 'Post', 'costs' => [
                ['shipping_code' => 'NL', 'weight_min' => 0, 'weight_max' => 100, 'price' => '3.95'],
                ['shipping_code' => 'NL', 'weight_min' => 101, 'weight_max' => 2000, 'price' => '6.95'],
            ]]]),
        ]);
        $this->artisan('bricqer:sync-shipping-methods')->assertSuccessful();
        $this->assertCount(2, ShippingMethod::query()->firstOrFail()->rate_bands);
        Saloon::fake([GetCountriesRequest::class => MockResponse::make([], 403)]);
        $this->artisan('bricqer:sync-shipping-methods')->assertFailed();
        $this->assertCount(2, ShippingMethod::query()->firstOrFail()->rate_bands);
    }

    private function order(): Order
    {
        $shipping = ShippingMethod::query()->create(['bricqer_id' => 3, 'name' => 'Post', 'country_ids' => ['NL' => 1]]);
        $address = ['name' => 'Test Buyer', 'line1' => 'Street', 'house_number' => '1', 'postal_code' => '1234AB', 'city' => 'Amsterdam', 'country_code' => 'NL'];
        $order = Order::factory()->paid()->create(['shipping_method_id' => $shipping->id]);
        $order->forceFill(['shipping_address' => $address, 'billing_address' => $address, 'stock_held' => true, 'payment_status' => 'paid'])->save();
        OrderItem::query()->create(['order_id' => $order->id, 'title' => 'Brick', 'unit_price_cents' => 250, 'quantity' => 2, 'source_allocations' => [['definition_id' => 123, 'quantity' => 2]]]);

        return $order;
    }
}
