<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Build extends Model
{
    use HasFactory;

    // user_id, total_price, estimated_power, compatibility_status, status and share_token
    // are deliberately not fillable — user_id is set via $user->builds()->create(), and the
    // rest are computed/generated exclusively by BuildService, never by direct user input.
    protected $fillable = [
        'name',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BuildItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
