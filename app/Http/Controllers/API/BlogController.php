<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\BlogLike;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\BlogResource;
use App\Http\Requests\StoreBlogRequest;
use App\Services\ActivityService;

class BlogController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ActivityService $activityService)
    {
    }

    /**
     * Display a listing of published blogs.
     */
    public function index()
    {
        $blogs = Blog::with('user')
            ->where('is_published', true)
            ->withCount('comments')
            ->withCount('likes')
            ->latest()
            ->paginate(15);

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
        // $this->authorize('create', Blog::class);

        $blog = $request->user()->blogs()->create($request->validated());
        if($request->has('tags')){
            $blog->tags()->sync($request->input('tags'));
        }
        $blog->loadCount(['comments', 'likes']);

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

        $blog->loadCount(['comments', 'likes']);

        return $this->successResponse(new BlogResource($blog->load('user')), 'Blog retrieved successfully');
    }

    /**
     * Update the specified blog.
     */
    public function update(Request $request, Blog $blog)
    {
        $this->authorize('update', $blog);
        $atts=$request->validate([
            'title' => 'sometimes|string',
            'body' => 'sometimes|string',
            'tags'=>'array',
            'tags.*'=>'exists:tags,id',
            'is_published' => 'sometimes|boolean'
        ]);
        $blog->update($atts);
        if(isset($atts['tags'])){
            $blog->tags()->sync($atts['tags']);
        }
        $blog->loadCount(['comments', 'likes']);

        return $this->successResponse(new BlogResource($blog->load('user')), 'Blog updated successfully');
    }

    /**
     * Remove the specified blog.
     */
    public function destroy(Blog $blog)
    {
        $this->authorize('delete', $blog);

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
            $isLiked = false;
        } else {
            BlogLike::create([
                'user_id' => $request->user()->id,
                'blog_id' => $blog->id,
            ]);
            $isLiked = true;

            $this->activityService->logUserInteraction(
                $request->user(),
                $blog,
                'blog_liked'
            );
        }

        $blog->loadCount('likes');

        return $this->successResponse([
            'is_liked' => $isLiked,
            'likes_count' => $blog->likes_count,
        ], $isLiked ? 'Blog liked' : 'Blog unliked');
    }

    public function drafts(Request $request)
    {
        $blogs = $request->user()->blogs()
            ->with('user')
            ->where('is_published', false)
            ->withCount('comments', 'likes')
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse(
            BlogResource::collection($blogs),
            'Draft blogs retrieved successfully'
        );
    }
}
