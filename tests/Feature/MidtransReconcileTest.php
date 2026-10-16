<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransReconcileTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.midtrans.server_key' => 'server-key-test']);
        $this->order = Order::create(['order_id' => 'ORDER-1001', 'amount' => 150000]);
    }

    public function test_order_paid_without_a_notification_is_synced(): void
    {
        Http::fake(['*/v2/ORDER-1001/status' => Http::response([
            'order_id' => 'ORDER-1001',
            'status_code' => '200',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'gross_amount' => '150000.00',
        ])]);

        $this->travel(20)->minutes();
        $this->artisan('midtrans:reconcile')
            ->expectsOutput('ORDER-1001: pending -> paid')
            ->assertSuccessful();

        $this->assertSame('paid', $this->order->fresh()->status);
        // Basic auth: the Server Key as username, empty password.
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('server-key-test:')));
    }

    public function test_unpaid_snap_transaction_stays_pending(): void
    {
        Http::fake(['*' => Http::response([
            'status_code' => '404',
            'status_message' => "Transaction doesn't exist.",
        ], 404)]);

        $this->travel(20)->minutes();
        $this->artisan('midtrans:reconcile')->assertSuccessful();

        $this->assertSame('pending', $this->order->fresh()->status);
    }
}
