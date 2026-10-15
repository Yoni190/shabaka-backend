<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexOrderRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\AdminOrderDetailResource;
use App\Http\Resources\AdminOrderListResource;
use App\Models\Order;
use App\Services\Admin\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * List orders
     *
     * Returns a paginated list of all orders, newest first. Supports
     * `search` (order number or business name), `status`, `payment_status`,
     * `sort` (created_at|order_number|total_amount), `direction`,
     * `page`, and `per_page` (max 100) query parameters.
     */
    public function index(IndexOrderRequest $request): AnonymousResourceCollection
    {
        return AdminOrderListResource::collection(
            $this->orderService->paginate($request->validated()),
        );
    }

    /**
     * Show an order
     *
     * Returns complete details for a single order including items, address
     * snapshots, and submitting business/user information.
     */
    public function show(Order $order): AdminOrderDetailResource
    {
        return new AdminOrderDetailResource(
            $this->orderService->find($order->id),
        );
    }

    /**
     * Update an order's status
     *
     * Changes the order's lifecycle status. Invalid transitions are rejected
     * with a 422. Transitioning to `cancelled` restores stock exactly once,
     * guarded by `stock_restored_at`. Payment status is never modified here.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $updated = $this->orderService->updateStatus(
            $order,
            OrderStatus::from($request->validated('status')),
        );

        return (new AdminOrderDetailResource($updated))
            ->additional(['message' => 'Order status updated successfully'])
            ->response();
    }
}