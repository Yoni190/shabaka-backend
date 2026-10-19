<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'payment_id',
    'expected_amount',
    'expected_currency',
    'status',
    'provider_status',
    'paid_at',
    'failed_at',
])]
class PaymentAttempt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expected_amount' => 'integer',
            'paid_at'         => 'datetime',
            'failed_at'       => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}