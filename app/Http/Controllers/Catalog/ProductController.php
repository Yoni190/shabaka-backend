<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\Catalog\ProductService;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    /**
     * List products
     *
     * Returns a paginated list of active products for the storefront
     * catalogue page. Inactive products are never included.
     *
     * Supports:
     * - `page` — page number
     * - `per_page` — items per page (default 15, max 100)
     * - `search` — matches against product name and SKU
     * - `category_id` — filter by category
     * - `brand_id` — filter by brand
     * - `is_featured` — filter to featured (`1`) or non-featured (`0`) products
     * - `sort` — one of `name`, `price`, `stock_quantity`, `created_at`
     * - `direction` — `asc` or `desc` (default `desc`)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $this->productService->paginate(
                $request->only([
                    'search',
                    'category_id',
                    'brand_id',
                    'is_featured',
                    'per_page',
                    'sort',
                    'direction',
                ]),
            ),
        );
    }

    /**
     * Show a product
     *
     * Returns a single active product for the storefront product details page.
     * Inactive products are treated as not found. The response includes the
     * product's category, brand, and all images ordered by their display order.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($this->productService->findPublic($product));
    }
}