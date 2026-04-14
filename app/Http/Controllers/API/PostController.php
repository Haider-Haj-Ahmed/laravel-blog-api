<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Events\PostLiked;
use App\Http\Requests\StorePostRequest;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Like;
use App\Services\ActivityService;
use App\Services\PostRecommendationService;
use App\Services\RecommendationCacheService;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly PostRecommendationService $postRecommendationService,
        private readonly RecommendationCacheService $recommendationCacheService
    )
    {
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::with(['user', 'photos'])
            ->where('is_published', true)
            ->withCount('comments')
            ->withCount('likes')
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse(
            PostResource::collection($posts),
            'Posts retrieved successfully'
        );
    }

    public function recommended(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:30',
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : null;

        $recommendedPosts = $this->postRecommendationService->buildFeed(
            $request->user(),
            $page,
            $perPage
        );

        return $this->paginatedResponse(
            PostResource::collection($recommendedPosts),
            'Recommended posts retrieved successfully'
        );
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {

        $credentials = $request->validated();
        $uploadedPhotos = $this->extractUploadedPhotos($request);
        unset($credentials['photo'], $credentials['photos']);

        $post = $request->user()->posts()->create($credentials);
        $this->syncUploadedPhotos($post, $uploadedPhotos);
        $post->tags()->sync($credentials['tags'] ?? []); // Sync tags if provided

        // Load relationships and counts for consistency
        $post->load(['user', 'photos']);
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
        $post = Post::with(['user', 'photos'])
            ->withCount('comments')
            ->withCount('likes')
            ->find($id);

        if (!$post) {
            return $this->notFoundResponse('Post not found');
        }

        $viewer = auth('sanctum')->user();
        if (! $post->is_published && (! $viewer || $viewer->id !== $post->user_id)) {
            return $this->forbiddenResponse('You are not authorized to view this post');
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
            'code_language' => 'sometimes|nullable|string|max:50',
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'photos' => 'sometimes|array|max:4',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'is_published' => 'sometimes|boolean'
        ]);

        $uploadedPhotos = null;
        if ($request->hasFile('photo') || $request->hasFile('photos')) {
            $uploadedPhotos = $this->extractUploadedPhotos($request);
        }
        unset($validated['photo'], $validated['photos']);

        $post->update($validated);
        if ($uploadedPhotos !== null) {
            $this->syncUploadedPhotos($post, $uploadedPhotos);
        }

        // Load relationships and counts for consistency
        $post->load(['user', 'photos']);
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

            PostLiked::dispatch($post, $user);
        }

        $this->recommendationCacheService->bumpUserVersion($user->id);

        // Return updated post with like count
        $post->loadCount('likes');

        return $this->successResponse([
            'is_liked' => $isLiked,
            'likes_count' => $post->likes_count,
        ], $isLiked ? 'Post liked' : 'Post unliked');
    }

    public function drafts(Request $request)
    {
        $posts = $request->user()->posts()
            ->with(['user', 'photos'])
            ->where('is_published', false)
            ->withCount('comments', 'likes')
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse(
            PostResource::collection($posts),
            'Draft posts retrieved successfully'
        );
    }

    private function extractUploadedPhotos(Request $request): array
    {
        $uploadedPhotos = [];

        if ($request->hasFile('photo')) {
            $uploadedPhotos[] = $request->file('photo');
        }

        if ($request->hasFile('photos')) {
            $uploadedPhotos = array_merge($uploadedPhotos, $request->file('photos'));
        }

        if (count($uploadedPhotos) > 4) {
            throw ValidationException::withMessages([
                'photos' => ['A post may have at most 4 photos.'],
            ]);
        }

        return $uploadedPhotos;
    }

    private function syncUploadedPhotos(Post $post, array $uploadedPhotos): void
    {
        if ($post->photo && Storage::disk('public')->exists("post_photos/{$post->photo}")) {
            Storage::disk('public')->delete("post_photos/{$post->photo}");
        }

        foreach ($post->photos as $existingPhoto) {
            if (Storage::disk('public')->exists($existingPhoto->path)) {
                Storage::disk('public')->delete($existingPhoto->path);
            }
        }

        $post->photos()->delete();

        $firstPhotoName = null;
        foreach ($uploadedPhotos as $index => $photoFile) {
            $photoName = time() . '_' . $post->user_id . '_' . $index . '.' . $photoFile->getClientOriginalExtension();
            $storedPath = $photoFile->storeAs('post_photos', $photoName, 'public');

            $post->photos()->create([
                'path' => $storedPath,
                'sort_order' => $index,
            ]);

            if ($index === 0) {
                $firstPhotoName = $photoName;
            }
        }

        $post->forceFill(['photo' => $firstPhotoName])->save();
    }
     public function viewrs(Request $request,$id){
        $post = Post::find($id);
        if (!$post) {
            return $this->notFoundResponse('Post not found');
        }
        return $this->successResponse([
            'viewers' => $post->views()->with('user')->get()->map(function ($view) {
                return [
                    'id' => $view->user_id,
                    'username' => $view->user->username,
                    'viewed_at' => $view->created_at->toDateTimeString(),
                ];
            }),
        ], 'Post viewers retrieved successfully');
    }
}
