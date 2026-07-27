<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $service)
    {
    }

    public function show(Request $request): CartResource
    {
        $cart = $this->service->getOrCreateCart($request->user());

        return new CartResource($cart->load('items.product.images'));
    }

    public function storeItem(AddCartItemRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->validated('product_id'));

        $item = $this->service->addItem($request->user(), $product, $request->validated('quantity'));

        return (new CartItemResource($item->load('product.images')))->response()->setStatusCode(201);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem): CartItemResource
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);

        $item = $this->service->updateQuantity($cartItem, $request->validated('quantity'));

        return new CartItemResource($item->load('product.images'));
    }

    public function destroyItem(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);

        $this->service->removeItem($cartItem);

        return response()->json(null, 204);
    }
}
