<?php

declare(strict_types=1);

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'payment_status' => $this->paid_at ? 'paid' : $this->payment_status,
            'fulfilment_status' => $this->fulfilment_status,
            'sync_status' => $this->sync_status,
            'bricqer_status' => $this->bricqer_status,
            'tracking_carrier' => $this->tracking_carrier,
            'invoice_available' => $this->invoice_document_id !== null,
            'invoice_url' => $this->invoice_document_id && $request->user()?->id === $this->user_id ? route('account.orders.invoice', $this->id) : null,
            'summary_url' => $request->user()?->id === $this->user_id && $this->user_id !== null ? route('account.orders.summary', $this->id) : null,
            'payment_url' => $this->canPay() && $this->payment_provider === 'testing' && ! app()->environment('production') && \Illuminate\Support\Facades\Route::has('checkout.payment.simulate') ? route('checkout.payment.simulate', $this->id) : null,
            'billing_address' => $this->billing_address,
            'email' => $this->email,
            'name' => $this->name,
            'phone' => $this->phone,
            'shipping_company' => $this->shipping_company,
            'shipping_line1' => $this->shipping_line1,
            'shipping_line2' => $this->shipping_line2,
            'shipping_postal_code' => $this->shipping_postal_code,
            'shipping_city' => $this->shipping_city,
            'shipping_country_code' => $this->shipping_country_code,
            'subtotal_cents' => $this->subtotal_cents,
            'shipping_cents' => $this->shipping_cents,
            'total_cents' => $this->total_cents,
            'shipping_method_name' => $this->shipping_method_name,
            'payment_method' => $this->payment_method,
            'tracking_code' => $this->tracking_code,
            'tracking_url' => $this->tracking_url,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
