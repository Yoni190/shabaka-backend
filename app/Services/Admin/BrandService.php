<?php

namespace App\Services\Admin;

use App\Models\Brand;
use App\Services\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\Pagination\PerPage;

class BrandService
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;

    /** Columns a client is allowed to sort by. */
    private const SORTABLE = ['name', 'created_at'];

    public function __construct(
        private readonly FileStorageService $fileStorage,
    ) {}

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
        $image = $data['brand_image'] ?? null;
        unset($data['brand_image']);

        $brand = Brand::create($data);

        if ($image) {
            $path = $this->fileStorage->store($image, "brands/{$brand->id}");

            $brand->update(['brand_image' => $path]);
        }

        return $brand;
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
        $image = $data['brand_image'] ?? null;
        unset($data['brand_image']);

        $oldImage = $brand->brand_image;

        if ($image) {
            $data['brand_image'] = $this->fileStorage->store($image, "brands/{$brand->id}");
        }

        $brand->update($data);

        if ($image && $oldImage) {
            $this->fileStorage->delete($oldImage);
        }

        return $brand;
    }

    public function delete(Brand $brand): void
    {
        if ($brand->products()->exists()) {
            abort(409, 'This brand cannot be deleted because it has products assigned to it.');
        }

        if ($brand->brand_image) {
            $this->fileStorage->delete($brand->brand_image);
        }

        $brand->delete();
    }
}