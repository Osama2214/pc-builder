<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(StoreProductImageRequest $request, Product $product): AnonymousResourceCollection
    {
        $files = $request->file('images');
        $primaryIndex = $request->integer('primary_index', 0);
        $hasExistingPrimary = $product->images()->where('is_primary', true)->exists();

        $created = collect($files)->map(function ($file, $index) use ($product, $primaryIndex, $hasExistingPrimary) {
            $path = $file->store('products', 'public');

            return $product->images()->create([
                'image_path' => $path,
                'is_primary' => ! $hasExistingPrimary && $index === $primaryIndex,
            ]);
        });

        return ProductImageResource::collection($created);
    }

    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        if ($image->is_primary) {
            $product->images()->first()?->update(['is_primary' => true]);
        }

        return response()->json(null, 204);
    }

    public function setPrimary(Product $product, ProductImage $image): ProductImageResource
    {
        abort_unless($image->product_id === $product->id, 404);

        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return new ProductImageResource($image);
    }
}
