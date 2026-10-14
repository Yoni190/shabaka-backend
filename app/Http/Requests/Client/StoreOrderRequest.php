<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'shipping_address_id' => ['required', 'integer', 'exists:addresses,id'],
            'billing_address_id'  => ['nullable', 'integer', 'exists:addresses,id'],
            'notes'               => ['nullable', 'string', 'max:1000'],

            // Never trust client-supplied ownership, pricing, or status.
            'business_id'    => ['prohibited'],
            'user_id'        => ['prohibited'],
            'order_number'   => ['prohibited'],
            'status'         => ['prohibited'],
            'payment_status' => ['prohibited'],
            'subtotal'       => ['prohibited'],
            'total_amount'   => ['prohibited'],
            'currency'       => ['prohibited'],
        ];
    }
}