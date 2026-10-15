<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'order_number'    => $this->order_number,
            'business_id'     => $this->business_id,
            'business_name'   => $this->whenLoaded('business', fn () => $this->business->name),
            'user_name'       => $this->whenLoaded('user', fn () => $this->user?->name),
            'user_email'      => $this->whenLoaded('user', fn () => $this->user?->email),
            'status'          => $this->status->value,
            'payment_status'  => $this->payment_status->value,
            'subtotal'        => $this->subtotal,
            'tax_amount'      => $this->tax_amount,
            'shipping_amount' => $this->shipping_amount,
            'discount_amount' => $this->discount_amount,
            'total_amount'    => $this->total_amount,
            'currency'        => $this->currency,
            'created_at'      => $this->created_at,
        ];
    }
}