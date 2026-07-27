<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // logo is either a path stored by our own upload, or a raw external URL
            // pasted in directly — only the former needs resolving through the disk.
            'logo' => $this->logo
                ? (str_starts_with($this->logo, 'http://') || str_starts_with($this->logo, 'https://')
                    ? $this->logo
                    : Storage::disk('public')->url($this->logo))
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
