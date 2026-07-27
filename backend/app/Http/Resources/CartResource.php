<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items');

        $total = $items === null ? 0 : $items->sum(
            fn ($item) => (float) ($item->product->sale_price ?? $item->product->price) * $item->quantity
        );

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($items ?? []),
            'total' => round($total, 2),
        ];
    }
}
