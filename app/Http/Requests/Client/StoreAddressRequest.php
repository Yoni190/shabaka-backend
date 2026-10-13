<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
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
            'label'           => ['nullable', 'string', 'max:100'],
            'recipient_name'  => ['nullable', 'string', 'max:255'],
            'company_name'    => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'address_line_1'  => ['required', 'string', 'max:255'],
            'address_line_2'  => ['nullable', 'string', 'max:255'],
            'city'            => ['required', 'string', 'max:100'],
            'state_or_region' => ['nullable', 'string', 'max:100'],
            'postal_code'     => ['nullable', 'string', 'max:20'],
            'country_code'    => ['required', 'string', 'size:2'],
            'is_shipping'     => ['sometimes', 'boolean'],
            'is_billing'      => ['sometimes', 'boolean'],

            // Ownership is derived from the authenticated user; these must
            // never be client-controlled.
            'business_id' => ['prohibited'],
            'id'          => ['prohibited'],
        ];
    }
}