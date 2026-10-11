<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'brand_image'    => $this->brand_image,
            'brand_image_url' => $this->brand_image
                ? config('filesystems.disks.s3.url')
                    . '/storage/v1/object/public/product-images/'
                    . $this->brand_image
                : null,
            'products_count' => $this->whenCounted('products'),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}