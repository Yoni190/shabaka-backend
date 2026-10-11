<?php

namespace App\Services\Admin;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\Pagination\PerPage;

class BrandService
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;

    /** Columns a client is allowed to sort by. */
    private const SORTABLE = ['name', 'created_at'];

    /**
     * @param  array{search?: string, per_page?: int|string, sort?: string, direction?: string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = PerPage::resolve($filters['per_page'] ?? null);

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? $filters['sort']
            : 'name';

        $direction = strtolower($filters['direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return Brand::query()
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'),
            )
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): Brand
    {
        return Brand::create($data);
    }

    public function find(Brand $brand): Brand
    {
        return $brand->loadCount('products');
    }

    /**
     * @param  array{name: string}  $data
     */
    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand;
    }

    public function delete(Brand $brand): void
    {
        if ($brand->products()->exists()) {
            abort(409, 'This brand cannot be deleted because it has products assigned to it.');
        }

        $brand->delete();
    }
}