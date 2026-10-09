<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'is_featured'    => $this->is_featured,
            'category'       => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'brand'          => $this->whenLoaded('brand', fn () => [
                'id'   => $this->brand->id,
                'name' => $this->brand->name,
            ]),
            'images'         => $this->whenLoaded('images', fn () => $this->images->map(function ($image) {
                return [
                    'id'         => $image->id,
                    'url' => config('filesystems.disks.s3.url')
                        . '/storage/v1/object/public/product-images/'
                        . $image->path,
                    'path' => $image->path,
                    'is_primary' => $image->is_primary,
                    'sort_order' => $image->sort_order,
                ];
            })),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}