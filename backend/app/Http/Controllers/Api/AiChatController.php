<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ChatRequest;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiChatController extends Controller
{
    public function __construct(private AiChatService $service)
    {
    }

    public function chat(ChatRequest $request): JsonResponse
    {
        // Resolved manually rather than via 'auth:sanctum' middleware so guests can
        // still ask questions and get recommendations — only actions that mutate
        // data (add_to_cart, create_build) require a real logged-in user, enforced
        // inside the service itself.
        $user = $request->user('sanctum');

        try {
            $result = $this->service->chat($user, $request->validated('message'), $request->validated('history', []));
        } catch (Throwable $e) {
            // Broad catch is deliberate here: this is a boundary around a third-party
            // network call (timeouts, malformed responses, quota errors, ...) — none
            // of that should ever leak a stack trace to the client, even with
            // APP_DEBUG on.
            Log::error('AI chat request failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'The AI assistant is temporarily unavailable. Please try again.'], 502);
        }

        return response()->json(['data' => $result]);
    }
}
