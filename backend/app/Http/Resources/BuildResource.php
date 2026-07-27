<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'total_price' => (float) $this->total_price,
            'estimated_power' => $this->estimated_power,
            'compatibility_status' => $this->compatibility_status,
            'status' => $this->status,
            'is_public' => $this->is_public,
            'share_token' => $this->is_public ? $this->share_token : null,
            'items' => BuildItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
