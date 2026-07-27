<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Banner\StoreBannerRequest;
use App\Http\Requests\Banner\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Guests and the storefront only ever need active banners; admins managing the list
        // (e.g. the admin banners page) need to see inactive ones too so they can re-enable them.
        $query = Banner::query()->orderBy('sort_order')->orderBy('id');

        if (! $request->user('sanctum')?->isAdmin()) {
            $query->active();
        }

        return BannerResource::collection($query->get());
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $path = $request->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'image_path' => $path,
            'link_url' => $request->validated('link_url'),
            'sort_order' => $request->validated('sort_order', 0),
            // Set explicitly rather than relying on the DB column default — Eloquent doesn't
            // hydrate DB-applied defaults onto the in-memory model immediately after create().
            'is_active' => true,
        ]);

        return (new BannerResource($banner))->response()->setStatusCode(201);
    }

    public function update(UpdateBannerRequest $request, Banner $banner): BannerResource
    {
        $banner->update($request->validated());

        return new BannerResource($banner);
    }

    public function destroy(Banner $banner): JsonResponse
    {
        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();

        return response()->json(null, 204);
    }
}
