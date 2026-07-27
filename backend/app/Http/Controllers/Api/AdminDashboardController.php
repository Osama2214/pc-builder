<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    // Threshold for "low stock" — arbitrary but consistent everywhere it's surfaced,
    // so a product doesn't count as low here but not on the products list.
    private const LOW_STOCK_THRESHOLD = 5;

    public function stats(): JsonResponse
    {
        $recentOrders = Order::with(['items.product', 'address'])->latest()->take(5)->get();

        return response()->json([
            'data' => [
                'products' => [
                    'total' => Product::count(),
                    'active' => Product::where('is_active', true)->count(),
                    'out_of_stock' => Product::where('stock', 0)->count(),
                    'low_stock' => Product::where('stock', '>', 0)->where('stock', '<=', self::LOW_STOCK_THRESHOLD)->count(),
                ],
                'orders' => [
                    'total' => Order::count(),
                    'pending' => Order::where('status', 'pending')->count(),
                    'processing' => Order::where('status', 'processing')->count(),
                    'shipped' => Order::where('status', 'shipped')->count(),
                    'completed' => Order::where('status', 'completed')->count(),
                    'cancelled' => Order::where('status', 'cancelled')->count(),
                    // Cancelled orders already restore stock and never really happened
                    // financially, so they're excluded from revenue.
                    'revenue' => (float) Order::where('status', '!=', 'cancelled')->sum('total_price'),
                ],
                'reviews' => [
                    'pending' => Review::where('is_approved', false)->count(),
                ],
                'categories' => ['total' => Category::count()],
                'brands' => ['total' => Brand::count()],
                'users' => ['total' => User::where('role', 'user')->count()],
                'banners' => [
                    'total' => Banner::count(),
                    'active' => Banner::where('is_active', true)->count(),
                ],
                'recent_orders' => OrderResource::collection($recentOrders),
            ],
        ]);
    }
}
