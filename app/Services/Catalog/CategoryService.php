<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Pagination\PerPage;

class CategoryService
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;

    /** Columns a client is allowed to sort by. */
    private const SORTABLE = ['name', 'created_at'];

    /**
     * @param  array{
     *     search?: string,
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
            : 'name';

        $direction = strtolower($filters['direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return Category::query()
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where('name', 'like', '%'.$search.'%'),
            )
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }
}