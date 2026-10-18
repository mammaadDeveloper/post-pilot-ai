<?php

namespace App\Http\Controllers;

use App\Exceptions\PostContentGenerationException;
use App\Http\Requests\GeneratePostContentRequest;
use App\Services\GeneratePostContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PostContentController extends Controller
{
    /**
     * Generate stateless AI content for the authenticated user.
     */
    public function generate(
        GeneratePostContentRequest $request,
        GeneratePostContent $generator,
    ): JsonResponse {
        $timeout = (int) config('postpilot.timeout');

        if ($timeout < 1 || $timeout > 120) {
            return response()->json(['message' => 'Set AI_TIMEOUT to a value between 1 and 120 seconds.'], 503);
        }

        $lock = Cache::lock(
            'post-content-generation:'.$request->user()->getAuthIdentifier(),
            $timeout + 15,
        );

        if (! $lock->get()) {
            return response()->json([
                'message' => 'A content generation request is already in progress. Please wait for it to finish.',
            ], 409);
        }

        try {
            return response()->json($generator->handle($request->user(), $request->generationInput()));
        } catch (PostContentGenerationException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } finally {
            $lock->release();
        }
    }
}
