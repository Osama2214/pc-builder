<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Requests\Order\UpdateOrderPaymentStatusRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Address;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private OrderService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $request->user()->orders()->with(['items.product', 'address'])->latest()->paginate(20)
        );
    }

    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $query = Order::with(['items.product', 'address', 'user']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        return OrderResource::collection($query->latest()->paginate(20));
    }

    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return new OrderResource($order->load(['items.product', 'address', 'user']));
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $address = Address::findOrFail($request->validated('address_id'));

        $order = $this->service->checkout(
            $request->user(),
            $address,
            $request->validated('payment_method'),
            $request->validated('notes'),
        );

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, Order $order): OrderResource
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return new OrderResource($this->service->cancel($order));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        return new OrderResource($this->service->updateStatus($order, $request->validated('status')));
    }

    public function updatePaymentStatus(UpdateOrderPaymentStatusRequest $request, Order $order): OrderResource
    {
        return new OrderResource($this->service->updatePaymentStatus($order, $request->validated('payment_status')));
    }
}
