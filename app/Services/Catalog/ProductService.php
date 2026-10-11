<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Pagination\PerPage;

class ProductService
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;

    /** Columns a client is allowed to sort by. */
    private const SORTABLE = ['name', 'price', 'stock_quantity', 'created_at'];

    /**
     * Public product catalogue listing.
     *
     * Always restricted to active products — this is a storefront endpoint,
     * so inactive products are never exposed. The `is_active` query parameter
     * is intentionally not supported for the same reason.
     *
     * @param  array{
     *     search?: string,
     *     category_id?: int|string,
     *     brand_id?: int|string,
     *     is_featured?: bool|int|string,
     *     per_page?: int|string,
     *     sort?: string,
     *     direction?: string,
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = PerPage::resolve($filters['per_page'] ?? null);

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? $filters['sort']
            : 'created_at';

        $direction = strtolower($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return Product::query()
            ->where('is_active', true)
            ->with(['category', 'brand', 'images'])
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                }),
            )
            ->when(
                $this->hasFilter($filters, 'category_id'),
                fn (Builder $query) => $query->where('category_id', (int) $filters['category_id']),
            )
            ->when(
                $this->hasFilter($filters, 'brand_id'),
                fn (Builder $query) => $query->where('brand_id', (int) $filters['brand_id']),
            )
            ->when(
                $this->hasFilter($filters, 'is_featured'),
                fn (Builder $query) => $query->where(
                    'is_featured',
                    filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN),
                ),
            )
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasFilter(array $filters, string $key): bool
    {
        return array_key_exists($key, $filters)
            && $filters[$key] !== null
            && $filters[$key] !== '';
    }

    /**
     * Public product detail.
     *
     * Inactive products are treated as not found, so the public endpoint does
     * not leak the existence of products that are not live. Reuses the same
     * eager loads as `paginate()` so there is no N+1 risk.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function findPublic(Product $product): Product
    {
        if (! $product->is_active) {
            abort(404);
        }

        return $product->load([
            'category',
            'brand',
            'images' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);
    }
}