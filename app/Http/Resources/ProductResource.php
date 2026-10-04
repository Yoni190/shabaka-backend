<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'sku'            => $this->sku,
            'description'    => $this->description,
            'price'          => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'is_active'      => $this->is_active,
            'category'       => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'brand'          => $this->whenLoaded('brand', fn () => [
                'id'   => $this->brand->id,
                'name' => $this->brand->name,
            ]),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}