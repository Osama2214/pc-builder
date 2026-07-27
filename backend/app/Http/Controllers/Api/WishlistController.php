<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wishlist\StoreWishlistRequest;
use App\Http\Resources\WishlistResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WishlistController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return WishlistResource::collection(
            $request->user()->wishlists()->with('product.images')->latest()->get()
        );
    }

    public function store(StoreWishlistRequest $request): JsonResponse
    {
        $wishlist = $request->user()->wishlists()->firstOrCreate([
            'product_id' => $request->validated('product_id'),
        ]);

        return (new WishlistResource($wishlist->load('product.images')))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $request->user()->wishlists()->where('product_id', $product->id)->delete();

        return response()->json(null, 204);
    }
}
