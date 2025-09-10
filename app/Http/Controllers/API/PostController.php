<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\PostResource;

class PostController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::with('user')
            ->withCount('comments')
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse($posts, 'Posts retrieved successfully');
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {

        $credentials = $request->validated();

        $post = $request->user()->posts()->create($credentials);

        // Load relationships and return resource
        $post->load('user');

        return $this->createdResponse(
            new PostResource($post),
            'Post created successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $post = Post::with('user')->find($id);

        if (!$post) {
            return $this->notFoundResponse('Post not found');
        }

        return $this->successResponse(
            new PostResource($post),
            'Post retrieved successfully'
        );
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $post = Post::find($id);

        if (!$post) {
            return $this->notFoundResponse('Post not found');
        }

        // تحقق أن المستخدم صاحب المقال
        if ($request->user()->id !== $post->user_id) {
            return $this->forbiddenResponse('You are not authorized to update this post');
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'body' => 'string',
            'is_published' => 'boolean'
        ]);

        $post->update($validated);

        // Load relationships and return resource
        $post->load('user');

        return $this->successResponse(
            new PostResource($post),
            'Post updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $post = Post::find($id);

        if (!$post) {
            return $this->notFoundResponse('Post not found');
        }

        if ($request->user()->id !== $post->user_id) {
            return $this->forbiddenResponse('You are not authorized to delete this post');
        }

        $post->delete();

        return $this->successResponse(null, 'Post deleted successfully');
    }
}
