<?php

declare(strict_types=1);

namespace App\Domain\Bricqer\Services;

use App\Integrations\Bricqer\BricqerConnector;
use App\Integrations\Bricqer\Requests\Commerce\CreateContactRequest;
use App\Integrations\Bricqer\Requests\Commerce\CreateOrderRequest;
use App\Integrations\Bricqer\Requests\Commerce\GetOrderRequest;
use App\Integrations\Bricqer\Requests\Commerce\ListOrdersRequest;
use App\Models\Order;
use App\Models\ShippingMethod;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BricqerOrderService
{
    public function __construct(private BricqerConnector $connector) {}

    public function export(Order $order): void
    {
        if ($order->paid_at === null || $order->bricqer_order_id !== null) {
            return;
        }
        if (in_array($order->sync_status, ['submitting', 'ambiguous'], true)) {
            $this->reconcile($order);

            return;
        }
        $payload = $this->payload($order);
        if ($order->bricqer_contact_id === null) {
            $shipping = ShippingMethod::query()->findOrFail($order->shipping_method_id);
            $address = $order->shipping_address;
            $contact = $this->connector->send(new CreateContactRequest([
                'original_name' => $order->name, 'name' => $order->name,
                'original_address' => $order->shipping_line1,
                'street1' => $address['line1'] ?? $order->shipping_line1, 'street2' => $address['line2'] ?? '',
                'street_number' => $address['house_number'] ?? '', 'street_addition' => $address['house_addition'] ?? '',
                'postal_code' => $order->shipping_postal_code, 'city' => $order->shipping_city,
                'email' => $order->email, 'phone' => $order->phone,
                'country_id' => data_get($shipping->country_ids, $order->shipping_country_code),
                'remarks' => 'Collect2Connect staging '.$order->number.'; '.($address['company'] ?? ''),
            ]))->json();
            if (empty($contact['id'])) {
                throw new RuntimeException('Bricqer did not return a contact ID.');
            }
            $order->forceFill(['bricqer_contact_id' => $contact['id']])->save();
        }
        $payload['contact'] = $order->bricqer_contact_id;
        $order->forceFill(['sync_status' => 'submitting', 'sync_error' => null])->save();
        try {
            $response = $this->connector->send(new CreateOrderRequest($payload))->json();
            $id = $response['meta_id'] ?? null;
            if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1) {
                throw new RuntimeException('Bricqer accepted the request without a usable metadata ID; reconcile before retrying.');
            }
            $order->forceFill(['bricqer_order_id' => (string) $id, 'exported_at' => now(), 'sync_status' => 'exported'])->save();
            $this->sync($order);
        } catch (Throwable $exception) {
            $order->forceFill(['sync_status' => $order->bricqer_order_id ? 'exported' : 'ambiguous',
                'sync_error' => Str::limit($exception->getMessage(), 1000)])->save();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function payload(Order $order): array
    {
        $order->loadMissing('items');
        $shipping = ShippingMethod::query()->find($order->shipping_method_id);
        if (! $shipping?->bricqer_id || ! isset($shipping->country_ids[$order->shipping_country_code])) {
            throw new RuntimeException('Import Bricqer shipping methods and countries before exporting orders.');
        }
        if (! $order->shipping_address || ! $order->billing_address) {
            throw new RuntimeException('Historical order has no complete address snapshots; automatic export is disabled.');
        }
        $addressKeys = ['name', 'company', 'line1', 'line2', 'house_number', 'house_addition', 'postal_code', 'city', 'country_code'];
        foreach ($addressKeys as $key) {
            if (trim((string) ($order->shipping_address[$key] ?? '')) !== trim((string) ($order->billing_address[$key] ?? ''))) {
                throw new RuntimeException('Bricqer noshop supports one contact address. Separate billing requires manual ERP handling; no order was submitted.');
            }
        }
        $items = [];
        foreach ($order->items as $item) {
            $allocations = $item->source_allocations ?? [];
            if (array_sum(array_column($allocations, 'quantity')) !== $item->quantity) {
                throw new RuntimeException('Order line '.$item->id.' has incomplete Bricqer source allocations.');
            }
            foreach ($allocations as $allocation) {
                if ($allocation['definition_id'] < 1 || $allocation['quantity'] < 1) {
                    throw new RuntimeException('Invalid Bricqer source allocation.');
                }
                $items[] = ['definition_id' => $allocation['definition_id'], 'quantity' => $allocation['quantity'],
                    'picked' => 0, 'price' => number_format($item->unit_price_cents / 100, 2, '.', ''),
                    'comment' => 'TEST — SIMULATED PAYMENT', 'requires_attention' => true];
            }
        }
        if ($items === []) {
            throw new RuntimeException('Cannot export an empty order.');
        }

        return ['shipping_method' => $shipping->bricqer_id, 'paid' => true, 'send_invoice_email' => false,
            'payment_method' => 'Collect2Connect test payment', 'custom_marketplace' => config('bricqer.marketplace'),
            'custom_marketplace_order_id' => $order->number, 'remarks' => 'TEST — SIMULATED PAYMENT — '.$order->number,
            'enable_picker_mutations' => true,
            'batches' => [['definition_type' => 'lego', 'items' => $items]],
            'invoice_lines' => [['description' => $order->shipping_method_name, 'amount' => number_format($order->shipping_cents / 100, 2, '.', ''), 'vat' => true, 'shipping' => true]],
        ];
    }

    public function sync(Order $order): void
    {
        if (! $order->bricqer_order_id) {
            $this->reconcile($order);

            return;
        }
        $data = $this->connector->send(new GetOrderRequest((int) $order->bricqer_order_id))->json();
        $status = (string) ($data['status'] ?? 'UNKNOWN');
        $fulfilment = match ($status) {
            'OPEN', 'PENDING', 'PAID' => 'unfulfilled',
            'UPDATED', 'PROCESSING', 'READY' => 'processing',
            'PACKED', 'PICKUP' => 'packed',
            'SHIPPED' => 'shipped',
            'RECEIVED', 'COMPLETED' => 'completed',
            'CANCELLED' => 'cancelled',
            default => 'requires_attention',
        };
        /** @var list<array<string, mixed>> $invoices */
        $invoices = $data['invoice_set'] ?? [];
        $invoice = collect($invoices)->first(fn (array $invoice): bool => ($invoice['invoice_type'] ?? '') === 'standard' && ! empty($invoice['document']));
        $trackingUrl = data_get($data, 'shipment.track_trace_url');
        $order->forceFill([
            'bricqer_status' => $status, 'fulfilment_status' => $fulfilment,
            'sync_status' => 'synced', 'synced_at' => now(), 'sync_error' => $fulfilment === 'requires_attention' ? 'Bricqer status: '.$status : null,
            'tracking_code' => data_get($data, 'shipment.tracking_code', $data['shipping_tracking_no'] ?? null),
            'tracking_url' => is_string($trackingUrl) && preg_match('~^https?://~i', $trackingUrl) ? $trackingUrl : null,
            'tracking_carrier' => data_get($data, 'shipment.provider_id'),
            'invoice_document_id' => $invoice['document'] ?? $order->invoice_document_id,
        ])->save();
    }

    /** An uncertain POST is never repeated automatically. */
    public function reconcile(Order $order): void
    {
        $matches = [];
        for ($page = 1; $page <= 100; $page++) {
            $result = $this->connector->send(new ListOrdersRequest($page, ($order->created_at ?? now())->copy()->subMinute()->toIso8601String()))->json();
            foreach ($result['results'] ?? [] as $candidate) {
                $data = $this->connector->send(new GetOrderRequest((int) $candidate['id']))->json();
                $source = is_array($data['order'] ?? null) ? $data['order'] : json_decode((string) ($data['order'] ?? '{}'), true);
                if (($data['custom_marketplace_order_id'] ?? $source['custom_marketplace_order_id'] ?? null) === $order->number
                    && ($data['custom_marketplace'] ?? $source['custom_marketplace'] ?? null) === config('bricqer.marketplace')) {
                    $matches[] = $candidate['id'];
                }
            }
            if (empty($result['next'])) {
                break;
            }
        }
        if (count($matches) === 1) {
            $order->forceFill(['bricqer_order_id' => (string) $matches[0], 'exported_at' => now(), 'sync_status' => 'exported'])->save();
            $this->sync($order);

            return;
        }
        $order->forceFill(['sync_status' => 'ambiguous', 'sync_error' => 'Remote creation could not be established uniquely. Check Bricqer before retrying; no duplicate order was submitted.'])->save();
    }
}
