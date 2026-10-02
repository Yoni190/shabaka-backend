<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryService
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;

    /** Columns a client is allowed to sort by. */
    private const SORTABLE = ['name', 'created_at'];

    /**
     * @param  array{search?: string, per_page?: int|string, sort?: string, direction?: string}  $filters
     */
    public function paginate(User $admin, array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $perPage = max($perPage, 1);

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? $filters['sort']
            : 'name';

        $direction = strtolower($filters['direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return Category::query()
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
    public function create(User $admin, array $data): Category
    {
        return Category::create($data);
    }

    public function find(User $admin, Category $category): Category
    {
        return $category->loadCount('products');
    }

    /**
     * @param  array{name: string}  $data
     */
    public function update(User $admin, Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    public function delete(User $admin, Category $category): void
    {
        if ($category->products()->exists()) {
            abort(409, 'This category cannot be deleted because it has products assigned to it.');
        }

        $category->delete();
    }
}