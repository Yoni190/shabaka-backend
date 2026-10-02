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
     * Returns a paginated list of product categories. Supports `page`, `per_page`
     * (max 100), `search`, `sort` (name|created_at), and `direction` (asc|desc)
     * query parameters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            $this->categoryService->paginate(
                $request->only(['search', 'per_page', 'sort', 'direction']),
            ),
        );
    }

    /**
     * Create a category
     *
     * Creates a new product category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

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
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($this->categoryService->find($category));
    }

    /**
     * Update a category
     *
     * Updates an existing product category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->categoryService->update($category, $request->validated());

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
    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->delete($category);

        return response()->json(['message' => 'Category deleted successfully']);
    }
}