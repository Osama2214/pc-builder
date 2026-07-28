<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Same dual-mode handling as BrandResource's logo: an externally-hosted
            // reference image (manufacturer's own site) is stored as a plain URL,
            // an admin-uploaded file is stored as a relative path on the public disk.
            'url' => str_starts_with($this->image_path, 'http')
                ? $this->image_path
                : Storage::disk('public')->url($this->image_path),
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
        ];
    }
}
