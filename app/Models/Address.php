<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id',
    'label',
    'recipient_name',
    'company_name',
    'phone',
    'address_line_1',
    'address_line_2',
    'city',
    'state_or_region',
    'postal_code',
    'country_code',
    'is_shipping',
    'is_billing',
])]
class Address extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_shipping' => 'boolean',
            'is_billing'  => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}