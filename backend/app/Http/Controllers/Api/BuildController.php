<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Build\AddBuildItemRequest;
use App\Http\Requests\Build\ShareBuildRequest;
use App\Http\Requests\Build\StoreBuildRequest;
use App\Http\Requests\Build\UpdateBuildItemRequest;
use App\Http\Requests\Build\UpdateBuildRequest;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Resources\BenchmarkTargetResource;
use App\Http\Resources\BuildResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\Address;
use App\Models\BenchmarkTarget;
use App\Models\Build;
use App\Models\BuildItem;
use App\Models\Product;
use App\Services\BuildService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuildController extends Controller
{
    private const RELATIONS = ['items.product.category', 'items.product.brand', 'items.product.specification', 'items.product.images'];

    public function __construct(private BuildService $service, private OrderService $orderService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return BuildResource::collection(
            $request->user()->builds()->with(self::RELATIONS)->latest()->get()
        );
    }

    public function show(Request $request, Build $build): BuildResource
    {
        abort_unless($build->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return new BuildResource($build->load(self::RELATIONS));
    }

    public function showShared(string $token): BuildResource
    {
        $build = Build::where('share_token', $token)->where('is_public', true)->firstOrFail();

        return new BuildResource($build->load(self::RELATIONS));
    }

    public function store(StoreBuildRequest $request): JsonResponse
    {
        $build = $this->service->create($request->user(), $request->validated('name'));

        return (new BuildResource($build->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function update(UpdateBuildRequest $request, Build $build): BuildResource
    {
        abort_unless($build->user_id === $request->user()->id, 403);

        $build->update($request->validated());

        return new BuildResource($build->load(self::RELATIONS));
    }

    public function destroy(Request $request, Build $build): JsonResponse
    {
        abort_unless($build->user_id === $request->user()->id, 403);

        $build->delete();

        return response()->json(null, 204);
    }

    public function storeItem(AddBuildItemRequest $request, Build $build): BuildResource
    {
        abort_unless($build->user_id === $request->user()->id, 403);

        $product = Product::findOrFail($request->validated('product_id'));

        $this->service->addItem($build, $product, $request->validated('slot'), $request->validated('quantity') ?? 1);

        return new BuildResource($build->fresh(self::RELATIONS));
    }

    public function updateItem(UpdateBuildItemRequest $request, Build $build, BuildItem $item): BuildResource
    {
        abort_unless($build->user_id === $request->user()->id, 403);
        abort_unless($item->build_id === $build->id, 404);

        $this->service->updateItemQuantity($item, $request->validated('quantity'));

        return new BuildResource($build->fresh(self::RELATIONS));
    }

    public function destroyItem(Request $request, Build $build, BuildItem $item): BuildResource
    {
        abort_unless($build->user_id === $request->user()->id, 403);
        abort_unless($item->build_id === $build->id, 404);

        $this->service->removeItem($item);

        return new BuildResource($build->fresh(self::RELATIONS));
    }

    public function share(ShareBuildRequest $request, Build $build): BuildResource
    {
        abort_unless($build->user_id === $request->user()->id, 403);

        return new BuildResource($this->service->share($build, $request->validated('is_public')));
    }

    public function checkout(CheckoutRequest $request, Build $build): JsonResponse
    {
        $address = Address::findOrFail($request->validated('address_id'));

        $order = $this->orderService->checkoutBuild(
            $request->user(),
            $build,
            $address,
            $request->validated('payment_method'),
            $request->validated('notes'),
        );

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    /**
     * Rough performance estimate for the whole build on a given benchmark target:
     * "game" targets are GPU-bound so we use the GPU's own benchmark record,
     * "software" targets are CPU-bound so we use the CPU's. This is a simple
     * aggregation of existing component benchmarks, not a real bottleneck model.
     */
    public function predict(Request $request, Build $build): JsonResponse
    {
        // Resolved explicitly (not via 'auth:sanctum' middleware) so this route can
        // also serve guests viewing a publicly shared build.
        $viewer = $request->user('sanctum');

        abort_unless($build->is_public || $viewer?->id === $build->user_id || $viewer?->isAdmin(), 403);

        $target = BenchmarkTarget::findOrFail($request->query('benchmark_target_id'));
        $slot = $target->type === 'software' ? 'cpu' : 'gpu';

        $build->loadMissing(['items.product.category', 'items.product.brand', 'items.product.specification', 'items.product.images']);
        $product = $build->items->firstWhere('slot', $slot)?->product;

        if (! $product) {
            return response()->json([
                'message' => "This build has no {$slot} to estimate performance from.",
            ], 422);
        }

        $benchmark = $product->benchmarks()->where('benchmark_target_id', $target->id)->first();

        if (! $benchmark) {
            return response()->json([
                'message' => 'No benchmark data available for this component on this target.',
            ], 404);
        }

        return response()->json([
            'benchmark_target' => new BenchmarkTargetResource($target),
            'based_on_slot' => $slot,
            'based_on_product' => new ProductResource($product),
            'fps' => $benchmark->fps,
            'score' => $benchmark->score,
            'unit' => $benchmark->unit,
            'note' => "Estimated from the {$slot} alone; actual results may vary due to other system factors.",
        ]);
    }
}
