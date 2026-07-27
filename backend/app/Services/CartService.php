<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreateCart(User $user): Cart
    {
        return $user->cart()->firstOrCreate([]);
    }

    /**
     * Add a product to the cart, enforcing business rule 2: the product must be
     * active and in stock, adding an already-present product increases its
     * quantity instead of creating a duplicate row, and the total requested
     * quantity may never exceed available stock.
     */
    public function addItem(User $user, Product $product, int $quantity): CartItem
    {
        $this->assertPurchasable($product);

        $cart = $this->getOrCreateCart($user);
        $existing = $cart->items()->where('product_id', $product->id)->first();
        $newQuantity = ($existing?->quantity ?? 0) + $quantity;

        $this->assertWithinStock($product, $newQuantity);

        return $cart->items()->updateOrCreate(
            ['product_id' => $product->id],
            ['quantity' => $newQuantity],
        );
    }

    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        $this->assertWithinStock($item->product, $quantity);

        $item->update(['quantity' => $quantity]);

        return $item;
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    private function assertPurchasable(Product $product): void
    {
        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => ['This product is not available.'],
            ]);
        }

        if ($product->stock < 1) {
            throw ValidationException::withMessages([
                'product_id' => ['This product is out of stock.'],
            ]);
        }
    }

    private function assertWithinStock(Product $product, int $requestedQuantity): void
    {
        if ($requestedQuantity > $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$product->stock} left in stock."],
            ]);
        }
    }
}
