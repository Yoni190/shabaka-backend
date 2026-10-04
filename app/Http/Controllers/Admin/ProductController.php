<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Admin\ProductService;
use Illuminate\Http\JsonResponse;
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
     * Returns a paginated list of products with their category and brand.
     *
     * Supports:
     * - `page` — page number
     * - `per_page` — items per page (default 15, max 100)
     * - `search` — matches against product name and SKU
     * - `category_id` — filter by category
     * - `brand_id` — filter by brand
     * - `is_active` — filter by active status (`1` or `0`)
     * - `sort` — one of `name`, `price`, `stock_quantity`, `created_at`
     * - `direction` — `asc` or `desc`
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $this->productService->paginate(
                $request->only([
                    'search',
                    'category_id',
                    'brand_id',
                    'is_active',
                    'per_page',
                    'sort',
                    'direction',
                ]),
            ),
        );
    }

    /**
     * Create a product
     *
     * Creates a new product with its category and brand associations.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        return (new ProductResource($product))
            ->additional(['message' => 'Product created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a product
     *
     * Returns a single product with its category and brand.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($this->productService->find($product));
    }

    /**
     * Update a product
     *
     * Updates an existing product. The product's own SKU is ignored when
     * checking SKU uniqueness.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->update($product, $request->validated());

        return (new ProductResource($product))
            ->additional(['message' => 'Product updated successfully'])
            ->response();
    }

    /**
     * Delete a product
     *
     * Deletes a product. Product image management is handled separately and
     * is not part of this endpoint.
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return response()->json(['message' => 'Product deleted successfully']);
    }
}