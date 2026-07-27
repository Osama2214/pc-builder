<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Benchmark\StoreBenchmarkRequest;
use App\Http\Requests\Benchmark\UpdateBenchmarkRequest;
use App\Http\Resources\BenchmarkResource;
use App\Models\Benchmark;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BenchmarkController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        return BenchmarkResource::collection(
            $product->benchmarks()->with('benchmarkTarget')->latest()->get()
        );
    }

    public function store(StoreBenchmarkRequest $request, Product $product): JsonResponse
    {
        $benchmark = $product->benchmarks()->create($request->validated());

        return (new BenchmarkResource($benchmark->load('benchmarkTarget')))->response()->setStatusCode(201);
    }

    public function update(UpdateBenchmarkRequest $request, Benchmark $benchmark): BenchmarkResource
    {
        $benchmark->update($request->validated());

        return new BenchmarkResource($benchmark->load('benchmarkTarget'));
    }

    public function destroy(Benchmark $benchmark): JsonResponse
    {
        $benchmark->delete();

        return response()->json(null, 204);
    }
}
