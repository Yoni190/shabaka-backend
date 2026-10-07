<?php

namespace App\Services\Admin;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;

    /** Columns a client is allowed to sort by. */
    private const SORTABLE = ['name', 'price', 'stock_quantity', 'created_at'];

    /**
     * @param  array{
     *     search?: string,
     *     category_id?: int|string,
     *     brand_id?: int|string,
     *     is_active?: bool|int|string,
     *     per_page?: int|string,
     *     sort?: string,
     *     direction?: string,
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? $filters['sort']
            : 'name';

        $direction = strtolower($filters['direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return Product::query()
            ->with(['category', 'brand'])
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
                $this->hasFilter($filters, 'is_active'),
                fn (Builder $query) => $query->where(
                    'is_active',
                    filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN),
                ),
            )
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        $images = $data['images'] ?? [];
        unset($data['images']);

        $product = Product::create($data);

        if (!empty($images)) {
            foreach ($images as $index => $imageFile) {
                $path = $imageFile->store('products', 's3');

                $product->images()->create([
                    'path'       => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        return $product->load(['category', 'brand', 'images']);
    }

    public function find(Product $product): Product
    {
        return $product->load(['category', 'brand']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->load(['category', 'brand']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * Detects whether a filter was actually supplied, treating "0" / "false"
     * as a real value rather than as an absent filter.
     *
     * @param  array<string, mixed>  $filters
     */
    private function hasFilter(array $filters, string $key): bool
    {
        return array_key_exists($key, $filters)
            && $filters[$key] !== null
            && $filters[$key] !== '';
    }
}