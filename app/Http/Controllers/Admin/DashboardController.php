<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * Get admin dashboard summary
     *
     * Returns catalogue and customer statistics plus the most recently
     * registered business customers for the admin dashboard homepage.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->summary(),
        ]);
    }
}