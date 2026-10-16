<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MidtransNotificationController
{
    public function __invoke(Request $request)
    {
        // An empty key would make the signature computable by anyone.
        $serverKey = config('services.midtrans.server_key')
            ?: throw new RuntimeException('MIDTRANS_SERVER_KEY is not set.');

        $expected = hash('sha512', $request->input('order_id')
            .$request->input('status_code')
            .$request->input('gross_amount')
            .$serverKey);

        Log::info('Midtrans notification', $request->only('order_id', 'transaction_status', 'status_code'));

        // Not sent by Midtrans, or the server key is from the other environment (sandbox vs production).
        abort_unless(hash_equals($expected, (string) $request->input('signature_key')), 403);

        Order::where('order_id', $request->input('order_id'))
            ->firstOrFail()
            ->applyMidtransStatus($request->all());

        return response('OK');
    }
}
