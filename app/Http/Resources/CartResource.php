<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'items'    => CartItemResource::collection($this->whenLoaded('items')),
            'subtotal' => $this->subtotal(),
        ];
    }

    /**
     * Cart subtotal as a decimal string, calculated in integer cents to
     * avoid floating-point rounding. Excludes taxes, shipping, and
     * discounts — those are checkout concerns, not cart concerns.
     */
    private function subtotal(): string
    {
        if (! $this->relationLoaded('items')) {
            return '0.00';
        }

        $totalCents = 0;

        foreach ($this->items as $item) {
            $unitCents = (int) round(((float) $item->product->price) * 100);
            $totalCents += $unitCents * $item->quantity;
        }

        return number_format($totalCents / 100, 2, '.', '');
    }
}