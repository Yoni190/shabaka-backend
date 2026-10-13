<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'product_id' => $this->product_id,
            'quantity'   => $this->quantity,
            'unit_price' => $this->product->price,
            'subtotal'   => $this->subtotal(),
            'product'    => [
                'id'             => $this->product->id,
                'name'           => $this->product->name,
                'sku'            => $this->product->sku,
                'price'          => $this->product->price,
                'stock_quantity' => $this->product->stock_quantity,
                'is_active'      => $this->product->is_active,
                'image'          => $this->primaryImage(),
            ],
        ];
    }

    /**
     * Line subtotal as a decimal string, calculated in integer cents to
     * avoid floating-point rounding.
     */
    private function subtotal(): string
    {
        $unitCents = (int) round(((float) $this->product->price) * 100);
        $totalCents = $unitCents * $this->quantity;

        return number_format($totalCents / 100, 2, '.', '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function primaryImage(): ?array
    {
        $image = $this->product->images->first();

        if (! $image) {
            return null;
        }

        return [
            'id'         => $image->id,
            'path'       => $image->path,
            'url'        => config('filesystems.disks.s3.url')
                . '/storage/v1/object/public/images/'
                . $image->path,
            'is_primary' => $image->is_primary,
        ];
    }
}