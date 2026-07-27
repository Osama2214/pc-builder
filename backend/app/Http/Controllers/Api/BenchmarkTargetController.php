<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BenchmarkTarget\StoreBenchmarkTargetRequest;
use App\Http\Requests\BenchmarkTarget\UpdateBenchmarkTargetRequest;
use App\Http\Resources\BenchmarkTargetResource;
use App\Models\BenchmarkTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BenchmarkTargetController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BenchmarkTargetResource::collection(BenchmarkTarget::orderBy('name')->get());
    }

    public function show(BenchmarkTarget $benchmarkTarget): BenchmarkTargetResource
    {
        return new BenchmarkTargetResource($benchmarkTarget);
    }

    public function store(StoreBenchmarkTargetRequest $request): JsonResponse
    {
        $target = BenchmarkTarget::create($request->validated());

        return (new BenchmarkTargetResource($target))->response()->setStatusCode(201);
    }

    public function update(UpdateBenchmarkTargetRequest $request, BenchmarkTarget $benchmarkTarget): BenchmarkTargetResource
    {
        $benchmarkTarget->update($request->validated());

        return new BenchmarkTargetResource($benchmarkTarget);
    }

    public function destroy(BenchmarkTarget $benchmarkTarget): JsonResponse
    {
        if ($benchmarkTarget->benchmarks()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a benchmark target that still has benchmark records.',
            ], 409);
        }

        $benchmarkTarget->delete();

        return response()->json(null, 204);
    }
}
