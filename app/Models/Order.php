<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['order_id', 'amount', 'status'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    /**
     * Apply a Midtrans transaction status, from a notification or the Get Status API.
     *
     * Only a pending order changes, in one conditional UPDATE, so a duplicate
     * or late notification changes nothing. Returns true if the order changed.
     */
    public function applyMidtransStatus(array $trx): bool
    {
        $status = $trx['transaction_status'] ?? null;

        // The three fields Midtrans says to check for a successful payment.
        $paid = ($trx['status_code'] ?? null) === '200'
            && in_array($status, ['capture', 'settlement'], true)
            && ($trx['fraud_status'] ?? 'accept') === 'accept';

        $changes = match (true) {
            $paid => ['status' => 'paid', 'paid_at' => now()],
            in_array($status, ['deny', 'cancel', 'expire', 'failure'], true) => ['status' => 'failed'],
            default => null, // pending, or a status this shop doesn't act on
        };

        return $changes !== null && static::whereKey($this->id)
            ->where('status', 'pending')
            ->update($changes) === 1;
    }
}
