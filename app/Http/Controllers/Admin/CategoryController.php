<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Admin\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * List categories
     *
     * Returns a paginated list of product categories.
     * Supports `page`, `per_page` (max 100), `search`, `sort` (name|created_at),
     * and `direction` (asc|desc) query parameters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = $this->categoryService->paginate(
            $request->user(),
            $request->only(['search', 'per_page', 'sort', 'direction']),
        );

        return CategoryResource::collection($categories);
    }

    /**
     * Create a category
     *
     * Creates a new product category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            $request->user(),
            $request->validated(),
        );

        return (new CategoryResource($category))
            ->additional(['message' => 'Category created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a category
     *
     * Returns a single product category together with its product count.
     */
    public function show(Request $request, Category $category): CategoryResource
    {
        return new CategoryResource(
            $this->categoryService->find($request->user(), $category),
        );
    }

    /**
     * Update a category
     *
     * Updates an existing product category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->categoryService->update(
            $request->user(),
            $category,
            $request->validated(),
        );

        return (new CategoryResource($category))
            ->additional(['message' => 'Category updated successfully'])
            ->response();
    }

    /**
     * Delete a category
     *
     * Deletes a product category. Fails with `409 Conflict` if the category
     * still has products assigned to it.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->categoryService->delete($request->user(), $category);

        return response()->json(['message' => 'Category deleted successfully']);
    }
}