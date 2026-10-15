<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderDetailResource extends JsonResource
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

            'business'         => $this->whenLoaded('business', fn () => [
                'id'   => $this->business->id,
                'name' => $this->business->name,
            ]),
            'user'             => $this->whenLoaded('user', fn () => $this->user ? [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ] : null),

            'shipping_address' => $this->shipping_address_snapshot,
            'billing_address'  => $this->billing_address_snapshot,

            'items'            => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_name' => $item->product_name,
                'sku'          => $item->sku,
                'quantity'     => $item->quantity,
                'unit_price'   => $item->unit_price,
                'subtotal'     => $item->subtotal,
            ])),

            'stock_restored_at' => $this->stock_restored_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}