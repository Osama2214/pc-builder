<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Benchmark extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'benchmark_target_id',
        'resolution',
        'quality',
        'fps',
        'score',
        'unit',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function benchmarkTarget(): BelongsTo
    {
        return $this->belongsTo(BenchmarkTarget::class);
    }
}
