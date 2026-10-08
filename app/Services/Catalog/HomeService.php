<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class HomeService
{
    /** Number of categories shown on the storefront homepage. */
    private const HOME_CATEGORY_LIMIT = 5;

    /** Number of featured products shown on the storefront homepage. */
    private const HOME_PRODUCT_LIMIT = 8;

    /**
     * Categories shown on the storefront homepage.
     *
     * Limited at the database level. If fewer categories exist than the limit,
     * all available categories are returned.
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()
            ->orderBy('created_at')
            ->orderBy('id')          // deterministic tie-breaker when created_at matches
            ->limit(self::HOME_CATEGORY_LIMIT)
            ->get();
    }

    /**
     * Featured products shown on the storefront homepage.
     *
     * Only active + featured products are returned. Result is limited at the
     * database level. If fewer featured products exist than the limit, all of
     * them are returned.
     *
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('is_featured', true)
            ->with(['category', 'brand', 'images'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')       // deterministic tie-breaker for same-second inserts
            ->limit(self::HOME_PRODUCT_LIMIT)
            ->get();
    }
}