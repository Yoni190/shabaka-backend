<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Services\Catalog\BrandService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
    ) {}

    /**
     * List brands
     *
     * Returns all product brands ordered alphabetically by name, including
     * each brand's image path and image URL. Brands without an image return
     * null for the image fields. Intended for the public storefront brand
     * directory; no authentication is required.
     */
    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection($this->brandService->all());
    }
}