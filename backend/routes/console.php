<?php

use App\Services\OrderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('orders:expire', function (OrderService $orders) {
    $count = $orders->expireOverdue();
    $this->info("Expired {$count} unpaid order(s).");
})->purpose('Release seats held by orders that were not paid in time');

Schedule::command('orders:expire')->everyMinute()->withoutOverlapping();
