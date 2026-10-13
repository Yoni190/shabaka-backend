<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
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
            'label'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'recipient_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'           => ['sometimes', 'nullable', 'string', 'max:30'],
            'address_line_1'  => ['sometimes', 'required', 'string', 'max:255'],
            'address_line_2'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'            => ['sometimes', 'required', 'string', 'max:100'],
            'state_or_region' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'country_code'    => ['sometimes', 'required', 'string', 'size:2'],
            'is_shipping'     => ['sometimes', 'boolean'],
            'is_billing'      => ['sometimes', 'boolean'],

            'business_id' => ['prohibited'],
            'id'          => ['prohibited'],
        ];
    }
}