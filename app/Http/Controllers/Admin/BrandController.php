<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\Admin\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
    ) {}

    /**
     * List brands
     *
     * Returns a paginated list of product brands. Supports `page`, `per_page`
     * (max 100), `search` (by name), `sort` (name|created_at), and `direction`
     * (asc|desc) query parameters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return BrandResource::collection(
            $this->brandService->paginate(
                $request->only(['search', 'per_page', 'sort', 'direction']),
            ),
        );
    }

    /**
     * Create a brand
     *
     * Creates a new product brand.
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->brandService->create($request->validated());

        return (new BrandResource($brand))
            ->additional(['message' => 'Brand created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a brand
     *
     * Returns a single product brand together with its product count.
     */
    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($this->brandService->find($brand));
    }

    /**
     * Update a brand
     *
     * Updates an existing product brand.
     */
    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand = $this->brandService->update($brand, $request->validated());

        return (new BrandResource($brand))
            ->additional(['message' => 'Brand updated successfully'])
            ->response();
    }

    /**
     * Delete a brand
     *
     * Deletes a product brand. Fails with `409 Conflict` if the brand still
     * has products assigned to it.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        $this->brandService->delete($brand);

        return response()->json(['message' => 'Brand deleted successfully']);
    }
}