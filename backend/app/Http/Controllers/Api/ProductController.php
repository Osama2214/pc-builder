<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private const RELATIONS = ['category', 'brand', 'specification', 'images'];

    public function index(Request $request): AnonymousResourceCollection
    {
        // Guard is resolved explicitly (not via 'auth:sanctum' middleware) because this
        // route must also serve guests per business rule 8 — an admin browsing with a
        // valid token should still see inactive products, everyone else should not.
        $viewer = $request->user('sanctum');

        $query = Product::query()->with(self::RELATIONS);

        if (! $viewer?->isAdmin()) {
            $query->active();
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('stock_status')) {
            match ($request->string('stock_status')->toString()) {
                'out_of_stock' => $query->where('stock', 0),
                'low_stock' => $query->where('stock', '>', 0)->where('stock', '<=', 5),
                'in_stock' => $query->where('stock', '>', 0),
                default => null,
            };
        }

        return ProductResource::collection($query->latest()->paginate(20));
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $viewer = $request->user('sanctum');

        if (! $product->is_active && ! $viewer?->isAdmin()) {
            abort(404);
        }

        return new ProductResource($product->load(self::RELATIONS));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $specifications = $data['specifications'] ?? null;
        unset($data['specifications']);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['created_by'] = $request->user()->id;

        $product = Product::create($data);

        if ($specifications) {
            $product->specification()->create($specifications);
        }

        return (new ProductResource($product->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $data = $request->validated();
        $specifications = $data['specifications'] ?? null;
        unset($data['specifications']);

        $product->update($data);

        if ($specifications) {
            $product->specification()->updateOrCreate([], $specifications);
        }

        return new ProductResource($product->fresh(self::RELATIONS));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }
}
