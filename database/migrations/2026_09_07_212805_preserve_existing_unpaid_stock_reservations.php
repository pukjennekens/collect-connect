<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->where('status', 'pending_payment')->whereNotNull('stock_reserved_at')->update(['stock_held' => true]);
        DB::table('orders')->whereNotNull('paid_at')->update(['payment_status' => 'paid']);
    }

    public function down(): void
    {
        // Derived lifecycle data is retained; clearing it would release active reservations.
    }
};
