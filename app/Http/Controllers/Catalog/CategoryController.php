<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Services\Catalog\CategoryService;
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
     * Returns a paginated list of all product categories for the storefront
     * catalogue page.
     *
     * Supports:
     * - `page` — page number
     * - `per_page` — items per page (default 15, max 100)
     * - `search` — partial match against category name
     * - `sort` — one of `name`, `created_at`
     * - `direction` — `asc` or `desc`
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            $this->categoryService->paginate(
                $request->only(['search', 'per_page', 'sort', 'direction']),
            ),
        );
    }
}