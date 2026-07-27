<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuildResource;
use App\Http\Resources\ProductResource;
use App\Models\Build;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CompareController extends Controller
{
    private const MIN_ITEMS = 2;

    private const MAX_ITEMS = 5;

    public function products(Request $request): AnonymousResourceCollection
    {
        $ids = $this->parseIds($request);
        // Resolved explicitly so guests can still compare (business rule 8) while
        // an admin browsing with a valid token can also compare inactive products.
        $viewer = $request->user('sanctum');

        $query = Product::whereIn('id', $ids)
            ->with(['category', 'brand', 'specification', 'images', 'benchmarks.benchmarkTarget']);

        if (! $viewer?->isAdmin()) {
            $query->active();
        }

        return ProductResource::collection($query->get());
    }

    public function builds(Request $request): AnonymousResourceCollection
    {
        $ids = $this->parseIds($request);
        $user = $request->user('sanctum');

        $builds = Build::whereIn('id', $ids)
            ->with(['items.product.category', 'items.product.brand', 'items.product.specification'])
            ->get()
            ->filter(fn (Build $build) => $build->is_public || $build->user_id === $user?->id)
            ->values();

        return BuildResource::collection($builds);
    }

    private function parseIds(Request $request): array
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values();

        if ($ids->count() < self::MIN_ITEMS) {
            throw ValidationException::withMessages(['ids' => ['Provide at least '.self::MIN_ITEMS.' ids to compare.']]);
        }

        if ($ids->count() > self::MAX_ITEMS) {
            throw ValidationException::withMessages(['ids' => ['You can compare at most '.self::MAX_ITEMS.' items at once.']]);
        }

        return $ids->all();
    }
}
