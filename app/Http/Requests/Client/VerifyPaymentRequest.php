<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
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
            'payment_id' => ['required', 'string', 'min:1', 'max:100'],
            'order_id'   => ['required', 'integer', 'exists:orders,id'],

            'amount'         => ['prohibited'],
            'currency'       => ['prohibited'],
            'status'         => ['prohibited'],
            'payment_status' => ['prohibited'],
            'business_id'    => ['prohibited'],
        ];
    }
}