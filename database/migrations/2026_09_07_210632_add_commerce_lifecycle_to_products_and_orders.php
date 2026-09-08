<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->integer('bricqer_stock')->nullable();
            $table->json('source_definitions')->nullable();
            $table->string('commerce_title')->nullable();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('checkout_token', 64)->nullable()->unique();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->boolean('stock_held')->default(false)->index();
            $table->string('payment_status')->default('pending');
            $table->string('fulfilment_status')->default('unfulfilled');
            $table->string('sync_status')->default('not_requested')->index();
            $table->text('sync_error')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedBigInteger('bricqer_contact_id')->nullable();
            $table->unsignedBigInteger('invoice_document_id')->nullable();
            $table->string('tracking_carrier')->nullable();
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->json('source_allocations')->nullable());
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('source_allocations'));
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['checkout_token']);
            $table->dropColumn(['checkout_token', 'billing_address', 'shipping_address', 'stock_held', 'payment_status', 'fulfilment_status', 'sync_status', 'sync_error', 'exported_at', 'synced_at', 'bricqer_contact_id', 'invoice_document_id', 'tracking_carrier']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['is_active', 'bricqer_stock', 'source_definitions', 'commerce_title']));
    }
};
