<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unitPrice = (float) ($this->product->sale_price ?? $this->product->price);

        return [
            'id' => $this->id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'quantity' => $this->quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($unitPrice * $this->quantity, 2),
            'created_at' => $this->created_at,
        ];
    }
}
