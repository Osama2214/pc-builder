<?php

namespace App\Services;

use App\Models\Build;
use App\Models\BuildItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuildService
{
    public const SINGLE_INSTANCE_SLOTS = ['cpu', 'motherboard', 'gpu', 'cooler', 'case', 'psu'];

    public const MULTI_INSTANCE_SLOTS = ['ram', 'storage'];

    public const ALL_SLOTS = [...self::SINGLE_INSTANCE_SLOTS, ...self::MULTI_INSTANCE_SLOTS];

    // Business rule 6: a build is only "complete" once every essential slot is filled.
    private const ESSENTIAL_SLOTS = ['cpu', 'motherboard', 'ram', 'psu', 'storage'];

    public function __construct(private CompatibilityService $compatibility)
    {
    }

    public function create(User $user, ?string $name = null): Build
    {
        $build = $user->builds()->create(['name' => $name]);

        // create() doesn't hydrate DB column defaults (status, compatibility_status)
        // back onto the in-memory model — refresh so the caller sees the real values.
        return $build->refresh();
    }

    /**
     * Add a product to a build slot. Single-instance slots (cpu, motherboard, gpu,
     * cooler, case, psu) replace whatever was already there. Multi-instance slots
     * (ram, storage) increase quantity for the same product or add a new row for
     * a different one (business rule 6).
     */
    public function addItem(Build $build, Product $product, string $slot, int $quantity = 1): BuildItem
    {
        abort_unless($build->status !== 'purchased', 422, 'A purchased build can no longer be edited.');

        if (! in_array($slot, self::ALL_SLOTS, true)) {
            throw ValidationException::withMessages(['slot' => ['Invalid slot.']]);
        }

        return DB::transaction(function () use ($build, $product, $slot, $quantity) {
            if (in_array($slot, self::SINGLE_INSTANCE_SLOTS, true)) {
                $build->items()->where('slot', $slot)->delete();

                $item = $build->items()->create([
                    'product_id' => $product->id,
                    'slot' => $slot,
                    'quantity' => 1,
                ]);
            } else {
                $existing = $build->items()->where('slot', $slot)->where('product_id', $product->id)->first();

                $item = $existing
                    ? tap($existing)->update(['quantity' => $existing->quantity + $quantity])
                    : $build->items()->create(['product_id' => $product->id, 'slot' => $slot, 'quantity' => $quantity]);
            }

            $this->recalculate($build);

            return $item;
        });
    }

    public function updateItemQuantity(BuildItem $item, int $quantity): BuildItem
    {
        abort_unless(in_array($item->slot, self::MULTI_INSTANCE_SLOTS, true), 422, 'Only RAM and storage items support quantity changes.');

        DB::transaction(function () use ($item, $quantity) {
            $item->update(['quantity' => $quantity]);
            $this->recalculate($item->build);
        });

        return $item->fresh();
    }

    public function removeItem(BuildItem $item): void
    {
        DB::transaction(function () use ($item) {
            $build = $item->build;
            $item->delete();
            $this->recalculate($build);
        });
    }

    /**
     * Business rule 6: recompute total_price, estimated_power and
     * compatibility_status after every slot change. status flips between
     * draft/complete based on essential-slot coverage but is never regressed
     * once a build has been purchased.
     */
    public function recalculate(Build $build): Build
    {
        $build->load('items.product.specification');

        $totalPrice = $build->items->sum(
            fn (BuildItem $item) => (float) ($item->product->sale_price ?? $item->product->price) * $item->quantity
        );

        $estimatedPower = $build->items->sum(
            fn (BuildItem $item) => (int) ($item->product->specification?->power_draw ?? 0) * $item->quantity
        );

        $filledSlots = $build->items->pluck('slot')->unique();
        $isComplete = collect(self::ESSENTIAL_SLOTS)->diff($filledSlots)->isEmpty();

        // Direct property assignment on purpose: these are computed fields, deliberately
        // excluded from $fillable so no request can set them directly (see Build model).
        $build->total_price = round($totalPrice, 2);
        $build->estimated_power = $estimatedPower;
        $build->compatibility_status = $this->compatibility->check($build);
        $build->status = $build->status === 'purchased' ? 'purchased' : ($isComplete ? 'complete' : 'draft');
        $build->save();

        return $build->fresh();
    }

    /**
     * Enable/refresh sharing. Decision on file: share_token only ever works
     * when is_public = true (enforced by BuildController::showShared()).
     */
    public function share(Build $build, bool $isPublic): Build
    {
        $build->is_public = $isPublic;

        // share_token is deliberately not fillable — only this Service may set it.
        if ($isPublic && ! $build->share_token) {
            $build->share_token = Str::random(32);
        }

        $build->save();

        return $build->fresh();
    }
}
