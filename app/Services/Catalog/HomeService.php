<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class HomeService
{
    /** Number of categories shown on the storefront homepage. */
    private const HOME_CATEGORY_LIMIT = 5;

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
}