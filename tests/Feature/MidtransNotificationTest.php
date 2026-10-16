<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel skips the CSRF check while running tests, but Midtrans
        // can't send a CSRF token. Turn the check back on, as in production.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        config(['services.midtrans.server_key' => 'server-key-test']);
        $this->order = Order::create(['order_id' => 'ORDER-1001', 'amount' => 150000]);
    }

    public function test_settlement_marks_the_order_paid(): void
    {
        $this->postJson('/midtrans/notification', $this->notification())->assertOk();

        $this->assertSame('paid', $this->order->fresh()->status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->postJson('/midtrans/notification', $this->notification(['signature_key' => 'palsu']))
            ->assertForbidden();

        $this->assertSame('pending', $this->order->fresh()->status);
    }

    public function test_duplicate_and_late_notifications_change_nothing(): void
    {
        $this->postJson('/midtrans/notification', $this->notification())->assertOk();
        $paidAt = $this->order->fresh()->paid_at;

        $this->travel(5)->minutes();
        $this->postJson('/midtrans/notification', $this->notification())->assertOk();
        $this->postJson('/midtrans/notification', $this->notification([
            'transaction_status' => 'pending',
            'status_code' => '201',
        ]))->assertOk();

        $order = $this->order->fresh();
        $this->assertSame('paid', $order->status);
        $this->assertEquals($paidAt, $order->paid_at);
    }

    /** A notification shaped like Midtrans', signed with the test server key. */
    private function notification(array $overrides = []): array
    {
        $n = array_merge([
            'order_id' => 'ORDER-1001',
            'status_code' => '200',
            'gross_amount' => '150000.00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'bank_transfer',
        ], $overrides);

        $n['signature_key'] ??= hash('sha512', $n['order_id'].$n['status_code'].$n['gross_amount'].'server-key-test');

        return $n;
    }
}
