<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    // order_id is deliberately not fillable — always set via $order->items()->create()
    // so an item can only ever be written into an order the caller already owns/loaded.
    // build_id is set internally by the checkout Service (which build this line fulfils,
    // if any) — never taken directly from client input.
    protected $fillable = [
        'product_id',
        'build_id',
        'quantity',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class);
    }
}
