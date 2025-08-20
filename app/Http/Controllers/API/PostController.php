<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::with('user')
            ->withCount('comments')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $posts
        ]);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'is_published' => 'boolean'
        ]);

        $post = $request->user()->posts()->create($validated);

        return response()->json($post, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $post = Post::with('user')->find($id);

        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $post
        ]);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found',
            ], 404);
        }

        // تحقق أن المستخدم صاحب المقال
        if ($request->user()->id !== $post->user_id) {
            return response()->json(['error' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'body' => 'string',
            'is_published' => 'boolean'
        ]);

        $post->update($validated);

        return response()->json($post);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found',
            ], 404);
        }

        if ($request->user()->id !== $post->user_id) {
            return response()->json(['error' => 'غير مصرح'], 403);
        }

        $post->delete();

        return response()->json(['message' => 'Post has been deleted successfully']);
    }
}
