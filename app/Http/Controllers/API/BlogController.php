<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Events\BlogLiked;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\BlogLike;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\BlogResource;
use App\Http\Requests\StoreBlogRequest;
use App\Http\Requests\UpdateBlogRequest;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BlogController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ActivityService $activityService)
    {
    }

    /**
     * Display a listing of published blogs.
     */
    public function index(Request $request)
    {
        $viewerId = auth('sanctum')->id();

        $blogsQuery = Blog::with('user')
            ->where('is_published', true)
            ->latest();

        if ($viewerId) {
            $blogsQuery->withExists([
                'views as is_viewed' => fn ($query) => $query->where('user_id', $viewerId),
            ]);
        }

        $blogs = $blogsQuery->paginate(15);

        return $this->paginatedResponse(
            BlogResource::collection($blogs),
            'Blogs retrieved successfully'
        );
    }

    /**
     * Store a newly created blog.
     */
    public function store(StoreBlogRequest $request)
    {
        $this->authorize('create', Blog::class);
        $storedPaths = [];
        try{
        $blog=DB::transaction(function () use ($request, &$storedPaths) {
            $blog = $request->user()->blogs()->create([
                'user_id' => $request->user()->id,
                'title' => $request->input('title'),
                'subtitle' => $request->input('subtitle'),
                'reading_time'=>$request->input('reading_time'),
                'is_published' => $request->input('is_published', false),
            ]);
            if($request->hasFile('cover_image')){
                $path = $request->file('cover_image')->store('cover_images', 'public');
                $storedPaths[] = $path;
                $blog->cover_image_path = $path;
                $blog->save();
            }
            if($request->has('tags')){
                $blog->tags()->sync($request->input('tags'));
            }
            foreach ($request->validated()['sections'] as $index => $sectionData) {
                Log::error($sectionData);
                $section = $blog->sections()->create([
                    'title' => $sectionData['title'],
                    'content' => $sectionData['content'],
                    'order' => $sectionData['order'],
                ]);
                $image = $request->file("sections.$index.image");
                if ($image) {
                    $path = $image->store('section_images', 'public');
                    $storedPaths[] = $path;
                    $section->image_path = $path;
                    $section->save();
                }
                // $blog->sections()->save($section);
            }
            return $blog;
        });
        }catch(\Exception $e){
            Log::error('Error creating blog: '.$e->getMessage());

            foreach ($storedPaths as $path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            }

            return $this->errorResponse('Failed to create blog', 500);
        }

        // $blog = $request->user()->blogs()->create($request->validated());
        // if($request->has('tags')){
        //     $blog->tags()->sync($request->input('tags'));
        // }
        if ($blog->is_published) {
            User::whereKey($request->user()->id)->increment('published_blogs_count');
        }
        $blog->load(['tags','sections']);

        return $this->createdResponse(
            new BlogResource($blog->load('user')),
            'Blog created successfully'
        );
    }

    /**
     * Display the specified blog.
     */
    public function show(Blog $blog)
    {
        $viewer = auth('sanctum')->user();

        if (!$blog->is_published && (! $viewer || $viewer->id !== $blog->user_id)) {
            return $this->forbiddenResponse('You are not authorized to view this blog');
        }

        if ($viewer) {
            $blog->loadExists([
                'views as is_viewed' => fn ($query) => $query->where('user_id', $viewer->id),
            ]);
        }

        $blog->load(['tags','sections']);

        return $this->successResponse(new BlogResource($blog->load('user')), 'Blog retrieved successfully');
    }

    /**
     * Update the specified blog.
     */
    public function update(UpdateBlogRequest $request, Blog $blog)
    {
        $this->authorize('update', $blog);

        $wasPublished = (bool) $blog->is_published;
        $validated = $request->validated();
        $shouldMarkModified = $this->blogContentChanged($blog, $validated, $request->hasFile('cover_image'), ($validated['remove_cover_image'] ?? false));
        $newCoverImagePath = null;
        $oldCoverImagePathToDelete = null;

        try {
            DB::transaction(function () use ($request, $blog, $validated, $shouldMarkModified, &$newCoverImagePath, &$oldCoverImagePathToDelete): void {
                $attributes = $validated;

                if ($request->hasFile('cover_image')) {
                    $newCoverImagePath = $request->file('cover_image')->store('cover_images', 'public');
                    if ($blog->cover_image_path) {
                        $oldCoverImagePathToDelete = $blog->cover_image_path;
                    }
                    $attributes['cover_image_path'] = $newCoverImagePath;
                }

                if (($validated['remove_cover_image'] ?? false) === true) {
                    if (! $oldCoverImagePathToDelete && $blog->cover_image_path) {
                        $oldCoverImagePathToDelete = $blog->cover_image_path;
                    }
                    $attributes['cover_image_path'] = null;
                }

                if (array_key_exists('tags', $validated)) {
                    $blog->tags()->sync($validated['tags'] ?? []);
                }

                unset($attributes['cover_image']);
                unset($attributes['remove_cover_image']);
                unset($attributes['tags']);

                if (! empty($attributes)) {
                    $blog->update($attributes);
                }

                if ($shouldMarkModified) {
                    $blog->forceFill(['is_modified' => true])->save();
                }
            });
        } catch (\Throwable $e) {
            if ($newCoverImagePath) {
                Storage::disk('public')->delete($newCoverImagePath);
            }

            Log::error('Error updating blog: ' . $e->getMessage());

            return $this->errorResponse('Failed to update blog', 500);
        }

        if ($oldCoverImagePathToDelete) {
            Storage::disk('public')->delete($oldCoverImagePathToDelete);
        }

        $this->syncPublishedBlogCounter($blog->user_id, $wasPublished, (bool) $blog->is_published);

        return $this->successResponse(
            new BlogResource($blog->load(['user', 'tags', 'sections'])),
            'Blog updated successfully'
        );
    }

    /**
     * Remove the specified blog.
     */
    public function destroy(Blog $blog)
    {
        $this->authorize('delete', $blog);

        if ($blog->is_published) {
            User::whereKey($blog->user_id)->where('published_blogs_count', '>', 0)->decrement('published_blogs_count');
        }
        $blog->delete();

        return $this->successResponse(null, 'Blog deleted successfully');
    }

    public function toggleLike(Request $request, Blog $blog)
    {
        $like = BlogLike::query()
            ->where('user_id', $request->user()->id)
            ->where('blog_id', $blog->id)
            ->first();

        if ($like) {
            $like->delete();
            $this->activityService->purgeUserInteraction($request->user(), $blog, 'blog_liked');
            $isLiked = false;
        } else {
            BlogLike::create([
                'user_id' => $request->user()->id,
                'blog_id' => $blog->id,
            ]);
            $isLiked = true;

            BlogLiked::dispatch($blog, $request->user());
        }

        $blog->refresh();

        return $this->successResponse([
            'is_liked' => $isLiked,
            'likes_count' => $blog->likes_count,
        ], $isLiked ? 'Blog liked' : 'Blog unliked');
    }

    public function drafts(Request $request)
    {
        $blogsQuery = $request->user()->blogs()
            ->with('user')
            ->where('is_published', false)
            ->latest();

        $blogsQuery->withExists([
            'views as is_viewed' => fn ($query) => $query->where('user_id', $request->user()->id),
        ]);

        $blogs = $blogsQuery->paginate(15);

        return $this->paginatedResponse(
            BlogResource::collection($blogs),
            'Draft blogs retrieved successfully'
        );
    }
    public function viewrs(Request $request,$id){
        $blog = Blog::find($id);
        if (!$blog) {
            return $this->notFoundResponse('Blog not found');
        }
        return $this->successResponse([
            'viewers' => $blog->views()->with('user')->get()->map(function ($view) {
                return [
                    'id' => $view->user_id,
                    'username' => $view->user->username,
                    'viewed_at' => $view->created_at->toDateTimeString(),
                ];
            }),
        ], 'Blog viewers retrieved successfully');
    }

    private function syncPublishedBlogCounter(int $userId, bool $wasPublished, bool $isPublished): void
    {
        if ($wasPublished === $isPublished) {
            return;
        }

        if ($isPublished) {
            User::whereKey($userId)->increment('published_blogs_count');
            return;
        }

        User::whereKey($userId)->where('published_blogs_count', '>', 0)->decrement('published_blogs_count');
    }

    private function blogContentChanged(Blog $blog, array $validated, bool $hasCoverImage, bool $removeCoverImage): bool
    {
        foreach (['title', 'subtitle', 'reading_time'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($blog->{$field} !== $validated[$field]) {
                return true;
            }
        }

        if ($hasCoverImage) {
            return true;
        }

        if ($removeCoverImage && $blog->cover_image_path) {
            return true;
        }

        return false;
    }
}
