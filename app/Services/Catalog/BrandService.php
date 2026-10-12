<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

class BrandService
{
    /**
     * All brands, ordered alphabetically by name.
     *
     * The storefront brand directory needs the complete brand list rather
     * than a paginated subset, so this deliberately returns everything.
     *
     * @return Collection<int, Brand>
     */
    public function all(): Collection
    {
        return Brand::query()
            ->orderBy('name')
            ->get();
    }
}