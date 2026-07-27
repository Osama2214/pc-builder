<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        return ReviewResource::collection(
            $product->reviews()->approved()->with('user')->latest()->paginate(20)
        );
    }

    public function pending(): AnonymousResourceCollection
    {
        return ReviewResource::collection(
            Review::where('is_approved', false)->with('user')->latest()->paginate(20)
        );
    }

    public function store(StoreReviewRequest $request, Product $product): JsonResponse
    {
        $review = $request->user()->reviews()->updateOrCreate(
            ['product_id' => $product->id],
            $request->validated(),
        );

        // Not fillable on purpose (users can't self-approve) — any create/edit of the
        // content must go back to pending until an admin reviews it again.
        $review->is_approved = false;
        $review->save();

        return (new ReviewResource($review->load('user')))->response()->setStatusCode(201);
    }

    public function approve(Request $request, Review $review): ReviewResource
    {
        $review->is_approved = true;
        $review->save();

        return new ReviewResource($review->load('user'));
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $review->delete();

        return response()->json(null, 204);
    }
}
