<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Services\Catalog\HomeService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HomeController extends Controller
{
    public function __construct(
        private readonly HomeService $homeService,
    ) {}

    /**
     * List homepage categories
     *
     * Returns up to 5 categories intended for display on the storefront
     * homepage. Fewer than 5 are returned if fewer categories exist. Ordering
     * is deterministic (`created_at` ascending, `id` as tie-breaker) so the
     * same categories appear on each request.
     */
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->homeService->categories());
    }

    /**
     * List homepage products
     *
     * Returns up to 8 featured products for display on the storefront
     * homepage. Only active products with `is_featured = true` are returned.
     * Fewer than 8 are returned if fewer featured products exist. Ordering is
     * `created_at` descending (newest first), with `id` as a tie-breaker.
     */
    public function products(): AnonymousResourceCollection
    {
        return ProductResource::collection($this->homeService->products());
    }
}