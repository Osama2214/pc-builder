<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Build;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public const STATUSES = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

    public const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    private const CANCELLABLE_STATUSES = ['pending'];

    /**
     * Turn the user's cart into an order, enforcing business rule 3: re-check
     * stock at execution time (not just at add-to-cart time), deduct it
     * immediately, and record historical per-item pricing.
     */
    public function checkout(User $user, Address $address, ?string $paymentMethod = null, ?string $notes = null): Order
    {
        abort_unless($address->user_id === $user->id, 403);

        return DB::transaction(function () use ($user, $address, $paymentMethod, $notes) {
            $cart = $user->cart()->with('items.product')->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => ['Your cart is empty.']]);
            }

            $lines = [];
            $total = 0;

            foreach ($cart->items as $cartItem) {
                $product = Product::whereKey($cartItem->product_id)->lockForUpdate()->first();

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'product_id' => ["\"{$cartItem->product->name}\" is no longer available."],
                    ]);
                }

                if ($cartItem->quantity > $product->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => ["Only {$product->stock} left of \"{$product->name}\"."],
                    ]);
                }

                $unitPrice = (float) ($product->sale_price ?? $product->price);
                $total += $unitPrice * $cartItem->quantity;
                $lines[] = ['product' => $product, 'quantity' => $cartItem->quantity, 'price' => $unitPrice];
            }

            $order = $user->orders()->create([
                'order_number' => $this->generateOrderNumber(),
                'address_id' => $address->id,
                'total_price' => $total,
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'price' => $line['price'],
                ]);

                $line['product']->decrement('stock', $line['quantity']);
            }

            $cart->items()->delete();

            return $order->load(['items.product', 'address']);
        });
    }

    /**
     * Buy an entire build in one order (business rule 6: a build only becomes
     * "purchased" once a real order containing all of its components exists).
     * Bypasses the cart entirely — the build's own items are the source of truth.
     */
    public function checkoutBuild(User $user, Build $build, Address $address, ?string $paymentMethod = null, ?string $notes = null): Order
    {
        abort_unless($build->user_id === $user->id, 403);
        abort_unless($address->user_id === $user->id, 403);

        if ($build->status === 'purchased') {
            throw ValidationException::withMessages([
                'build' => ['This build has already been purchased.'],
            ]);
        }

        if ($build->status !== 'complete') {
            throw ValidationException::withMessages([
                'build' => ['This build is not ready to purchase yet (missing essential components).'],
            ]);
        }

        return DB::transaction(function () use ($user, $build, $address, $paymentMethod, $notes) {
            $build->loadMissing('items.product');

            $lines = [];
            $total = 0;

            foreach ($build->items as $buildItem) {
                $product = Product::whereKey($buildItem->product_id)->lockForUpdate()->first();

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'product_id' => ["\"{$buildItem->product->name}\" is no longer available."],
                    ]);
                }

                if ($buildItem->quantity > $product->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => ["Only {$product->stock} left of \"{$product->name}\"."],
                    ]);
                }

                $unitPrice = (float) ($product->sale_price ?? $product->price);
                $total += $unitPrice * $buildItem->quantity;
                $lines[] = ['product' => $product, 'quantity' => $buildItem->quantity, 'price' => $unitPrice];
            }

            $order = $user->orders()->create([
                'order_number' => $this->generateOrderNumber(),
                'address_id' => $address->id,
                'total_price' => $total,
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'build_id' => $build->id,
                    'quantity' => $line['quantity'],
                    'price' => $line['price'],
                ]);

                $line['product']->decrement('stock', $line['quantity']);
            }

            // status is deliberately not fillable — set directly, bypassing mass assignment.
            $build->status = 'purchased';
            $build->save();

            return $order->load(['items.product', 'address']);
        });
    }

    /**
     * Cancel an order and restore stock for every line item (business rule 3).
     */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if (! in_array($order->status, self::CANCELLABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => ["An order with status \"{$order->status}\" can no longer be cancelled."],
                ]);
            }

            $order->loadMissing('items');

            foreach ($order->items as $item) {
                $item->product()->increment('stock', $item->quantity);
            }

            $order->update(['status' => 'cancelled']);

            return $order->fresh(['items.product', 'address']);
        });
    }

    /**
     * Admin-only free-form status change (any of self::STATUSES, not just the
     * customer's restricted cancel flow). Stock stays correct across the
     * transition either way: moving TO cancelled restores it, moving AWAY FROM
     * cancelled re-deducts it (failing if the stock is no longer available).
     */
    public function updateStatus(Order $order, string $newStatus): Order
    {
        if ($newStatus === $order->status) {
            return $order;
        }

        return DB::transaction(function () use ($order, $newStatus) {
            $order->loadMissing('items.product');

            $wasCancelled = $order->status === 'cancelled';
            $becomingCancelled = $newStatus === 'cancelled';

            if ($becomingCancelled && ! $wasCancelled) {
                foreach ($order->items as $item) {
                    $item->product()->increment('stock', $item->quantity);
                }
            } elseif (! $becomingCancelled && $wasCancelled) {
                foreach ($order->items as $item) {
                    $product = Product::whereKey($item->product_id)->lockForUpdate()->first();

                    if (! $product || $product->stock < $item->quantity) {
                        throw ValidationException::withMessages([
                            'status' => ["Cannot reactivate this order: only {$product?->stock} left of \"{$item->product->name}\"."],
                        ]);
                    }
                }

                foreach ($order->items as $item) {
                    $item->product()->decrement('stock', $item->quantity);
                }
            }

            $order->status = $newStatus;
            $order->save();

            return $order->fresh(['items.product', 'address']);
        });
    }

    public function updatePaymentStatus(Order $order, string $newStatus): Order
    {
        $order->payment_status = $newStatus;
        $order->save();

        return $order->fresh(['items.product', 'address']);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
