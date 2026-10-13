<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'label'           => $this->label,
            'recipient_name'  => $this->recipient_name,
            'company_name'    => $this->company_name,
            'phone'           => $this->phone,
            'address_line_1'  => $this->address_line_1,
            'address_line_2'  => $this->address_line_2,
            'city'            => $this->city,
            'state_or_region' => $this->state_or_region,
            'postal_code'     => $this->postal_code,
            'country_code'    => $this->country_code,
            'is_shipping'     => $this->is_shipping,
            'is_billing'      => $this->is_billing,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}