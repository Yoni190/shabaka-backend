<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreAddressRequest;
use App\Http\Requests\Client\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Services\Client\Address\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressService $addressService,
    ) {}

    /**
     * List addresses
     *
     * Returns all saved addresses belonging to the authenticated user's
     * business. Addresses belonging to other businesses are never included.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection(
            $this->addressService->list($request->user()),
        );
    }

    /**
     * Create an address
     *
     * Creates a new address owned by the authenticated user's business.
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addressService->create(
            $request->user(),
            $request->validated(),
        );

        return (new AddressResource($address))
            ->additional(['message' => 'Address created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an address
     *
     * Returns a single address owned by the authenticated user's business.
     * Addresses belonging to other businesses return 404.
     */
    public function show(Request $request, int $address): AddressResource
    {
        return new AddressResource(
            $this->addressService->find($request->user(), $address),
        );
    }

    /**
     * Update an address
     *
     * Updates a single address owned by the authenticated user's business.
     * Accepts partial updates. Addresses belonging to other businesses
     * return 404.
     */
    public function update(UpdateAddressRequest $request, int $address): JsonResponse
    {
        $updated = $this->addressService->update(
            $request->user(),
            $address,
            $request->validated(),
        );

        return (new AddressResource($updated))
            ->additional(['message' => 'Address updated successfully'])
            ->response();
    }

    /**
     * Delete an address
     *
     * Deletes a single address owned by the authenticated user's business.
     * Addresses belonging to other businesses return 404.
     */
    public function destroy(Request $request, int $address): JsonResponse
    {
        $this->addressService->delete($request->user(), $address);

        return response()->json(['message' => 'Address deleted successfully']);
    }
}