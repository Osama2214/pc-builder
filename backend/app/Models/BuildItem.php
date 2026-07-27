<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildItem extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    // build_id is deliberately not fillable — always set via $build->items()->create()
    // so an item can only ever be written into a build the caller already owns/loaded.
    protected $fillable = [
        'product_id',
        'slot',
        'quantity',
    ];

    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
