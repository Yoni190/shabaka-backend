<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\Client\Order\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * Place an order
     *
     * Creates an order from the authenticated user's business cart. The cart
     * must contain at least one item. Stock is decremented at order
     * placement; payment status is set to unpaid and is not updated here.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createFromCart(
            $request->user(),
            $request->validated(),
        );

        return (new OrderResource($order))
            ->additional(['message' => 'Order placed successfully'])
            ->response()
            ->setStatusCode(201);
    }
}