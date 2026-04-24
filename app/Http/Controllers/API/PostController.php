<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Events\PostLiked;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostContentRequest;
use App\Http\Requests\UpdatePostPhotosRequest;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Like;
use App\Models\PostPhoto;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\PostRecommendationService;
use App\Services\RecommendationCacheService;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;

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
    public function index(Request $request)
    {
        $viewerId = auth('sanctum')->id();

        $postsQuery = Post::with(['user', 'photos'])
            ->where('is_published', true)
            ->latest();

        if ($viewerId) {
            $postsQuery->withExists([
                'views as is_viewed' => fn ($query) => $query->where('user_id', $viewerId),
            ]);
        }

        $posts = $postsQuery->paginate(15);

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
        unset($credentials['photos']);

        $post = $request->user()->posts()->create($credentials);
        if ($post->is_published) {
            User::whereKey($request->user()->id)->increment('published_posts_count');
        }
        $this->syncUploadedPhotos($post, $uploadedPhotos);
        $post->tags()->sync($credentials['tags'] ?? []); // Sync tags if provided

        // Load relationships and counts for consistency
        $post->load(['user', 'photos']);

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
        $viewerId = auth('sanctum')->id();
        $postQuery = Post::with(['user', 'photos']);

        if ($viewerId) {
            $postQuery->withExists([
                'views as is_viewed' => fn ($query) => $query->where('user_id', $viewerId),
            ]);
        }

        $post = $postQuery->find($id);

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
    public function updateContent(UpdatePostContentRequest $request, $id)
    {
        $postOrResponse = $this->resolveOwnedPost($request, $id);
        if (! $postOrResponse instanceof Post) {
            return $postOrResponse;
        }

        $shouldMarkModified = $this->postContentChanged($postOrResponse, $request->validated());
        $wasPublished = (bool) $postOrResponse->is_published;
        $this->applyContentUpdate($postOrResponse, $request->validated());

        if ($shouldMarkModified) {
            $postOrResponse->forceFill(['is_modified' => true])->save();
        }

        $isPublished = (bool) $postOrResponse->is_published;
        $this->syncPublishedPostCounter(
            $request->user()->id,
            $wasPublished,
            $isPublished
        );

        return $this->returnUpdatedPostResponse($postOrResponse, 'Post content updated successfully');
    }

    public function addPhoto(UpdatePostPhotosRequest $request, $id)
    {
        $postOrResponse = $this->resolveOwnedPost($request, $id);
        if (! $postOrResponse instanceof Post) {
            return $postOrResponse;
        }

        if ($postOrResponse->photos()->count() >= 4) {
            return $this->validationErrorResponse([
                'photo' => ['A post cannot have more than 4 photos.'],
            ]);
        }

        $this->createPhotoForPost($postOrResponse, $request->file('photo'));
        $this->markAsModified($postOrResponse);

        return $this->returnUpdatedPostResponse($postOrResponse, 'Post photo added successfully');
    }

    public function replacePhoto(UpdatePostPhotosRequest $request, $id, $photoId)
    {
        $postOrResponse = $this->resolveOwnedPost($request, $id);
        if (! $postOrResponse instanceof Post) {
            return $postOrResponse;
        }

        $photoOrResponse = $this->resolveOwnedPhoto($postOrResponse, $photoId);
        if (! $photoOrResponse instanceof PostPhoto) {
            return $photoOrResponse;
        }

        $this->deleteStoredPhoto($photoOrResponse->path);
        $replacementFile = $request->file('photo');
        $replacementName = time() . '_' . $postOrResponse->user_id . '_' . $photoOrResponse->sort_order . '.' . $replacementFile->getClientOriginalExtension();
        $replacementPath = $replacementFile->storeAs('post_photos', $replacementName, 'public');
        $photoOrResponse->update(['path' => $replacementPath]);
        $this->markAsModified($postOrResponse);

        return $this->returnUpdatedPostResponse($postOrResponse, 'Post photo replaced successfully');
    }

    public function deletePhoto(Request $request, $id, $photoId)
    {
        $postOrResponse = $this->resolveOwnedPost($request, $id);
        if (! $postOrResponse instanceof Post) {
            return $postOrResponse;
        }

        $photoOrResponse = $this->resolveOwnedPhoto($postOrResponse, $photoId);
        if (! $photoOrResponse instanceof PostPhoto) {
            return $photoOrResponse;
        }

        $this->deleteStoredPhoto($photoOrResponse->path);
        $photoOrResponse->delete();
        $this->normalizePhotoSortOrder($postOrResponse);
        $this->markAsModified($postOrResponse);

        return $this->returnUpdatedPostResponse($postOrResponse, 'Post photo deleted successfully');
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

        if ($post->is_published) {
            User::whereKey($post->user_id)->where('published_posts_count', '>', 0)->decrement('published_posts_count');
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
            $this->activityService->purgeUserInteraction($user, $post, 'post_liked');
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
        $post->refresh();

        return $this->successResponse([
            'is_liked' => $isLiked,
            'likes_count' => $post->likes_count,
        ], $isLiked ? 'Post liked' : 'Post unliked');
    }

    public function drafts(Request $request)
    {
        $postsQuery = $request->user()->posts()
            ->with(['user', 'photos'])
            ->where('is_published', false)
            ->latest();

        $postsQuery->withExists([
            'views as is_viewed' => fn ($query) => $query->where('user_id', $request->user()->id),
        ]);

        $posts = $postsQuery->paginate(15);

        return $this->paginatedResponse(
            PostResource::collection($posts),
            'Draft posts retrieved successfully'
        );
    }

    private function extractUploadedPhotos(Request $request): array
    {
        return $request->hasFile('photos') ? $request->file('photos') : [];
    }

    private function resolveOwnedPost(Request $request, $id)
    {
        $post = Post::find($id);
        if (! $post) {
            return $this->notFoundResponse('Post not found');
        }

        if ($request->user()->id !== $post->user_id) {
            return $this->forbiddenResponse('You are not authorized to update this post');
        }

        return $post;
    }

    private function applyContentUpdate(Post $post, array $validated): void
    {
        $tags = $validated['tags'] ?? null;
        unset($validated['tags']);

        if (! empty($validated)) {
            $post->update($validated);
        }

        if ($tags !== null) {
            $post->tags()->sync($tags);
        }
    }

    private function returnUpdatedPostResponse(Post $post, string $message)
    {
        $post->load(['user', 'photos', 'tags']);

        return $this->successResponse(
            new PostResource($post),
            $message
        );
    }

    private function syncPublishedPostCounter(int $userId, bool $wasPublished, bool $isPublished): void
    {
        if ($wasPublished === $isPublished) {
            return;
        }

        if ($isPublished) {
            User::whereKey($userId)->increment('published_posts_count');
            return;
        }

        User::whereKey($userId)->where('published_posts_count', '>', 0)->decrement('published_posts_count');
    }

    private function resolveOwnedPhoto(Post $post, $photoId)
    {
        $photo = $post->photos()->where('id', $photoId)->first();
        if (! $photo) {
            return $this->notFoundResponse('Post photo not found');
        }

        return $photo;
    }

    private function createPhotoForPost(Post $post, $photoFile): void
    {
        $nextSortOrder = (int) ($post->photos()->max('sort_order') ?? -1) + 1;
        $photoName = time() . '_' . $post->user_id . '_' . $nextSortOrder . '.' . $photoFile->getClientOriginalExtension();
        $storedPath = $photoFile->storeAs('post_photos', $photoName, 'public');

        $post->photos()->create([
            'path' => $storedPath,
            'sort_order' => $nextSortOrder,
        ]);
    }

    private function deleteStoredPhoto(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function normalizePhotoSortOrder(Post $post): void
    {
        $post->photos()->orderBy('sort_order')->orderBy('id')->get()->each(function (PostPhoto $photo, int $index) {
            if ((int) $photo->sort_order !== $index) {
                $photo->update(['sort_order' => $index]);
            }
        });
    }

    private function syncUploadedPhotos(Post $post, array $uploadedPhotos): void
    {
        foreach ($post->photos as $existingPhoto) {
            if (Storage::disk('public')->exists($existingPhoto->path)) {
                Storage::disk('public')->delete($existingPhoto->path);
            }
        }

        $post->photos()->delete();

        foreach ($uploadedPhotos as $index => $photoFile) {
            $photoName = time() . '_' . $post->user_id . '_' . $index . '.' . $photoFile->getClientOriginalExtension();
            $storedPath = $photoFile->storeAs('post_photos', $photoName, 'public');

            $post->photos()->create([
                'path' => $storedPath,
                'sort_order' => $index,
            ]);
        }
    }

    private function postContentChanged(Post $post, array $validated): bool
    {
        foreach (['title', 'body', 'code', 'code_language'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($post->{$field} !== $validated[$field]) {
                return true;
            }
        }

        return false;
    }

    private function markAsModified(Post $post): void
    {
        if (! $post->is_modified) {
            $post->forceFill(['is_modified' => true])->save();
        }
    }
     public function viewrs(Request $request,$id){
        $post = Post::find($id);
        if (!$post) {
            return $this->notFoundResponse('Post not found');
        }
        if ($request->user()->id !== $post->user_id) {
            return $this->forbiddenResponse('You are not authorized to view post viewers');
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
