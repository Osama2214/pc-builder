<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BenchmarkController;
use App\Http\Controllers\Api\BenchmarkTargetController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\BuildController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompareController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Hit by an external keep-alive ping (see .github/workflows/keep-alive.yml) so the
// Render free-tier instance and the Neon Postgres compute don't both go to sleep
// between visits — a real query, not a static response, is what keeps Neon awake.
Route::get('/health', function () {
    DB::select('select 1');

    return response()->json(['status' => 'ok']);
});

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('addresses', AddressController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'storeItem']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroyItem']);

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy']);

    Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::get('/builds', [BuildController::class, 'index']);
    Route::post('/builds', [BuildController::class, 'store']);
    Route::get('/builds/{build}', [BuildController::class, 'show']);
    Route::patch('/builds/{build}', [BuildController::class, 'update']);
    Route::delete('/builds/{build}', [BuildController::class, 'destroy']);
    Route::post('/builds/{build}/items', [BuildController::class, 'storeItem']);
    Route::patch('/builds/{build}/items/{item}', [BuildController::class, 'updateItem']);
    Route::delete('/builds/{build}/items/{item}', [BuildController::class, 'destroyItem']);
    Route::post('/builds/{build}/share', [BuildController::class, 'share']);
    Route::post('/builds/{build}/checkout', [BuildController::class, 'checkout']);
});

// Public: guests may browse/search/view (business rule 8).
Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
Route::apiResource('brands', BrandController::class)->only(['index', 'show']);
Route::apiResource('products', ProductController::class)->only(['index', 'show']);
Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);
Route::get('/builds/shared/{token}', [BuildController::class, 'showShared']);
Route::get('/builds/{build}/predict', [BuildController::class, 'predict']);

Route::get('/benchmark-targets', [BenchmarkTargetController::class, 'index']);
Route::get('/benchmark-targets/{benchmarkTarget}', [BenchmarkTargetController::class, 'show']);
Route::get('/products/{product}/benchmarks', [BenchmarkController::class, 'index']);

Route::get('/compare/products', [CompareController::class, 'products']);
Route::get('/compare/builds', [CompareController::class, 'builds']);

Route::get('/banners', [BannerController::class, 'index']);

// Public so guests can ask questions too; add_to_cart/create_build tool calls
// check for a logged-in user inside AiChatService itself.
Route::post('/ai/chat', [AiChatController::class, 'chat'])->middleware('throttle:ai-chat');

// Admin-only writes (business rule 8: CRUD on Products/Categories/Brands is admin-only,
// plus review approval and order fulfillment).
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('brands', BrandController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);

    Route::post('/products/{product}/images', [ProductImageController::class, 'store']);
    Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy']);
    Route::patch('/products/{product}/images/{image}/primary', [ProductImageController::class, 'setPrimary']);

    Route::get('/reviews/pending', [ReviewController::class, 'pending']);
    Route::patch('/reviews/{review}/approve', [ReviewController::class, 'approve']);

    Route::get('/admin/stats', [AdminDashboardController::class, 'stats']);
    Route::get('/admin/orders', [OrderController::class, 'adminIndex']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::patch('/orders/{order}/payment-status', [OrderController::class, 'updatePaymentStatus']);

    Route::apiResource('benchmark-targets', BenchmarkTargetController::class)->only(['store', 'update', 'destroy']);
    Route::post('/products/{product}/benchmarks', [BenchmarkController::class, 'store']);
    Route::patch('/benchmarks/{benchmark}', [BenchmarkController::class, 'update']);
    Route::delete('/benchmarks/{benchmark}', [BenchmarkController::class, 'destroy']);

    Route::post('/banners', [BannerController::class, 'store']);
    Route::patch('/banners/{banner}', [BannerController::class, 'update']);
    Route::delete('/banners/{banner}', [BannerController::class, 'destroy']);
});
