<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status'       => $this['status'],
            'order_id'     => $this['order']->id,
            'order_number' => $this['order']->order_number,
        ];
    }
}