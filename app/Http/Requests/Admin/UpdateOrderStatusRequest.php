<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(OrderStatus::class)],

            // This endpoint changes status only. Everything else is
            // server-owned and must not be settable by the client.
            'payment_status' => ['prohibited'],
            'order_number'   => ['prohibited'],
            'business_id'    => ['prohibited'],
            'user_id'        => ['prohibited'],
            'subtotal'       => ['prohibited'],
            'tax_amount'     => ['prohibited'],
            'shipping_amount' => ['prohibited'],
            'discount_amount' => ['prohibited'],
            'total_amount'   => ['prohibited'],
            'currency'       => ['prohibited'],
        ];
    }
}