<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;
use App\Models\Like;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;

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
            ->withCount('likes')
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse(
            PostResource::collection($posts),
            'Posts retrieved successfully'
        );
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {

        $credentials = $request->validated();

        if ($request->hasFile('photo')) {
            $photoFile = $request->file('photo');
            $photoName = time() . '_' . $request->user()->id . '.' . $photoFile->getClientOriginalExtension();
            $photoFile->storeAs('post_photos', $photoName, 'public');
            $credentials['photo'] = $photoName;
        } else {
            unset($credentials['photo']);
        }

        $post = $request->user()->posts()->create($credentials);
        $post->tags()->sync($credentials['tags'] ?? []); // Sync tags if provided

        // Load relationships and counts for consistency
        $post->load('user');
        $post->loadCount(['comments', 'likes']);

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
        $post = Post::with('user')
            ->withCount('comments')
            ->withCount('likes')
            ->find($id);

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
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'code' => 'sometimes|nullable|string',
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'is_published' => 'sometimes|boolean'
        ]);

        if ($request->hasFile('photo')) {
            // Delete old photo if replacing it
            if ($post->photo && Storage::disk('public')->exists("post_photos/{$post->photo}")) {
                Storage::disk('public')->delete("post_photos/{$post->photo}");
            }

            $photoFile = $request->file('photo');
            $photoName = time() . '_' . $request->user()->id . '.' . $photoFile->getClientOriginalExtension();
            $photoFile->storeAs('post_photos', $photoName, 'public');
            $validated['photo'] = $photoName;
        } else {
            unset($validated['photo']);
        }

        $post->update($validated);

        // Load relationships and counts for consistency
        $post->load('user');
        $post->loadCount(['comments', 'likes']);

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

        // Delete stored photo if present
        if ($post->photo && Storage::disk('public')->exists("post_photos/{$post->photo}")) {
            Storage::disk('public')->delete("post_photos/{$post->photo}");
        }

        $post->delete();

        return $this->successResponse(null, 'Post deleted successfully');
    }

    /**
     * Toggle like on a post (Instagram-style)
     */
    public function toggleLike($postId)
    {
        $post = Post::find($postId);
        
        if (!$post) {
            return $this->notFoundResponse('Post not found');
        }

        $user = request()->user();
        
        $like = Like::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        if ($like) {
            $like->delete();
            $isLiked = false;
        } else {
            Like::create([
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);
            $isLiked = true;
        }

        // Return updated post with like count
        $post->loadCount('likes');
        
        return $this->successResponse([
            'is_liked' => $isLiked,
            'likes_count' => $post->likes_count,
        ], $isLiked ? 'Post liked' : 'Post unliked');
    }
}
