<?php

use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

// Safety net for notifications that never arrive: ask Midtrans directly.
Artisan::command('midtrans:reconcile', function () {
    Order::where('status', 'pending')
        ->where('created_at', '<', now()->subMinutes(15))
        ->each(function (Order $order) {
            $response = Http::withBasicAuth(config('services.midtrans.server_key'), '')
                ->acceptJson()
                ->get(config('services.midtrans.base_url')."/v2/{$order->order_id}/status");

            // 404: the buyer hasn't chosen a payment method in Snap yet.
            if ($response->json('status_code') === '404') {
                return;
            }

            $response->throw();

            if ($order->applyMidtransStatus($response->json())) {
                $this->info("{$order->order_id}: pending -> {$order->fresh()->status}");
            }
        });
})->purpose('Sync pending orders with the Midtrans Get Status API');

// Orders that are never paid stay 404 and get rechecked on every run.
// Add an age limit to the query if that list grows.
Schedule::command('midtrans:reconcile')->everyFiveMinutes()->withoutOverlapping();
