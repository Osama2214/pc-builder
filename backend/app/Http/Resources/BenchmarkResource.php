<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BenchmarkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'benchmark_target' => new BenchmarkTargetResource($this->whenLoaded('benchmarkTarget')),
            'resolution' => $this->resolution,
            'quality' => $this->quality,
            'fps' => $this->fps,
            'score' => $this->score,
            'unit' => $this->unit,
            'created_at' => $this->created_at,
        ];
    }
}
