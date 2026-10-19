<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number',
    'business_id',
    'user_id',
    'shipping_address_id',
    'billing_address_id',
    'shipping_address_snapshot',
    'billing_address_snapshot',
    'status',
    'payment_status',
    'subtotal',
    'tax_amount',
    'shipping_amount',
    'discount_amount',
    'total_amount',
    'currency',
    'notes',
    'stock_restored_at',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status'                    => OrderStatus::class,
            'payment_status'            => PaymentStatus::class,
            'subtotal'                  => 'decimal:2',
            'tax_amount'                => 'decimal:2',
            'shipping_amount'           => 'decimal:2',
            'discount_amount'           => 'decimal:2',
            'total_amount'              => 'decimal:2',
            'shipping_address_snapshot' => 'array',
            'billing_address_snapshot'  => 'array',
            'stock_restored_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}