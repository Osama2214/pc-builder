<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    public function __construct(private AddressService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection($request->user()->addresses()->latest()->get());
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->service->create($request->user(), $request->validated());

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function update(UpdateAddressRequest $request, Address $address): AddressResource
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        return new AddressResource($this->service->update($address, $request->validated()));
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        if ($address->orders()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an address that is used by an existing order.',
            ], 409);
        }

        $this->service->delete($address);

        return response()->json(null, 204);
    }
}
