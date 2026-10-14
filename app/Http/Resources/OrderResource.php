<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,
            'status'           => $this->status->value,
            'payment_status'   => $this->payment_status->value,
            'subtotal'         => $this->subtotal,
            'tax_amount'       => $this->tax_amount,
            'shipping_amount'  => $this->shipping_amount,
            'discount_amount'  => $this->discount_amount,
            'total_amount'     => $this->total_amount,
            'currency'         => $this->currency,
            'notes'            => $this->notes,
            'shipping_address' => $this->shipping_address_snapshot,
            'billing_address'  => $this->billing_address_snapshot,
            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}