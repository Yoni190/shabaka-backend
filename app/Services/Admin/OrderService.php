<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Support\Pagination\PerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /** Columns a client is allowed to sort by. */
    private const SORTABLE = ['created_at', 'order_number', 'total_amount'];

    /**
     * @param  array{
     *     search?: string,
     *     status?: string,
     *     payment_status?: string,
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
            : 'created_at';

        $direction = strtolower($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return Order::query()
            ->with(['business:id,name', 'user:id,name,email'])
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(function (Builder $q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhereHas(
                          'business',
                          fn (Builder $bq) => $bq->where('name', 'like', "%{$search}%"),
                      );
                }),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->when(
                $filters['payment_status'] ?? null,
                fn (Builder $query, string $status) => $query->where('payment_status', $status),
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')     // deterministic tie-break when created_at matches
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Order
    {
        return Order::with([
            'items',
            'business',
            'user',
            'shippingAddress',
            'billingAddress',
        ])->findOrFail($id);
    }

    /**
     * Apply a status transition. On cancellation, restores stock exactly once
     * per order, guarded by `stock_restored_at`.
     *
     * @throws ValidationException
     */
    public function updateStatus(Order $order, OrderStatus $newStatus): Order
    {
        return DB::transaction(function () use ($order, $newStatus) {
            // Lock the order row: concurrent admin actions serialize here.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === $newStatus) {
                throw ValidationException::withMessages([
                    'status' => ["The order is already {$newStatus->value}."],
                ]);
            }

            if (! $locked->status->canTransitionTo($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Cannot transition from {$locked->status->value} to {$newStatus->value}.",
                    ],
                ]);
            }

            if ($newStatus === OrderStatus::CANCELLED && $locked->stock_restored_at === null) {
                $this->restoreStock($locked);
                $locked->stock_restored_at = now();
            }

            $locked->status = $newStatus;
            $locked->save();

            return $locked->load([
                'items',
                'business',
                'user',
                'shippingAddress',
                'billingAddress',
            ]);
        });
    }

    /**
     * Return each order item's quantity to the referenced product's stock.
     * Items whose product has been deleted are silently skipped — there is
     * nothing to restore to.
     */
    private function restoreStock(Order $order): void
    {
        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return;
        }

        // Lock product rows so a concurrent order cannot decrement stock
        // between our read and our increment.
        $products = Product::whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($order->items as $item) {
            $product = $item->product_id ? $products->get($item->product_id) : null;

            if ($product) {
                $product->increment('stock_quantity', $item->quantity);
            }
        }
    }
}