<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function store(Request $request, Post $post): JsonResponse
    {
        $post->likes()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'liked' => true,
            'likes_count' => $post->likes()->count(),
        ], 201);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $post->likes()
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'liked' => false,
            'likes_count' => $post->likes()->count(),
        ]);
    }
}
