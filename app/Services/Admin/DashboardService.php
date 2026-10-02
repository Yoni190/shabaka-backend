<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class DashboardService
{
    /** Window (in days) that counts as "recently registered". */
    private const RECENT_DAYS = 30;

    /** Rows returned per "recent" list. */
    private const RECENT_LIMIT = 5;

    /**
     * Aggregate the admin dashboard summary.
     *
     * @return array<string, mixed>
     */
    public function summary(User $admin): array
    {
        return [
            'catalogue'        => $this->catalogue(),
            'customers'        => $this->customers(),
            'recent_customers' => $this->recentCustomers(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function catalogue(): array
    {
        return [
            'total_products'        => Product::count(),
            'active_products'       => Product::where('is_active', true)->count(),
            'inactive_products'     => Product::where('is_active', false)->count(),
            'total_categories'      => Category::count(),
            'total_brands'          => Brand::count(),
            'out_of_stock_products' => Product::where('stock_quantity', 0)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function customers(): array
    {
        return [
            'total_business_customers' => User::whereNotNull('business_id')->count(),
            'recently_registered'      => Business::where(
                'created_at',
                '>=',
                now()->subDays(self::RECENT_DAYS),
            )->count(),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function recentCustomers(): Collection
    {
        return User::query()
            ->whereNotNull('business_id')
            ->with('business:id,name')
            ->latest()
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'name', 'email', 'business_id', 'created_at']);
    }
}