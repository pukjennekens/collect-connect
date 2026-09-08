<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bricqer:sync')
    ->everyThirtyMinutes()
    ->withoutOverlapping(expiresAt: 60)
    ->onOneServer()
    ->runInBackground();

Schedule::command('bricqer:sync-shipping-methods')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('bricqer:import-weights')
    ->weeklyOn(1, '04:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('rebrickable:import-entity')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('orders:release-unpaid-stock')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('bricqer:sync-orders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('stock:notify-restocked')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
