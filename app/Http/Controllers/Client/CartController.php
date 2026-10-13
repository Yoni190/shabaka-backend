<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreCartItemRequest;
use App\Http\Requests\Client\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\Client\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    /**
     * Show the cart
     *
     * Returns the authenticated user's business cart with all items, current
     * product data, and calculated subtotals. Creates an empty cart on first
     * access. Items from other businesses are never included.
     */
    public function show(Request $request): CartResource
    {
        return new CartResource($this->cartService->view($request->user()));
    }

    /**
     * Add an item to the cart
     *
     * Adds the product to the authenticated user's business cart. If the
     * product is already present, its quantity is increased by the requested
     * amount. The resulting quantity (existing + requested) is validated
     * against available stock.
     */
    public function storeItem(StoreCartItemRequest $request): JsonResponse
    {
        $cart = $this->cartService->addItem($request->user(), $request->validated());

        return (new CartResource($cart))
            ->additional(['message' => 'Item added to cart'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a cart item
     *
     * Replaces the quantity of the specified cart item with the requested
     * value. Items belonging to other businesses return 404.
     */
    public function updateItem(UpdateCartItemRequest $request, int $item): JsonResponse
    {
        $cart = $this->cartService->updateItem(
            $request->user(),
            $item,
            (int) $request->validated('quantity'),
        );

        return (new CartResource($cart))
            ->additional(['message' => 'Cart updated'])
            ->response();
    }

    /**
     * Remove a cart item
     *
     * Removes the specified item from the authenticated user's business cart.
     * Items belonging to other businesses return 404.
     */
    public function destroyItem(Request $request, int $item): JsonResponse
    {
        $this->cartService->removeItem($request->user(), $item);

        return response()->json(['message' => 'Item removed from cart']);
    }

    /**
     * Clear the cart
     *
     * Removes all items from the authenticated user's business cart without
     * deleting the cart itself.
     */
    public function clearItems(Request $request): JsonResponse
    {
        $this->cartService->clear($request->user());

        return response()->json(['message' => 'Cart cleared']);
    }
}