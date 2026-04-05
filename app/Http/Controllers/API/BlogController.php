<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\BlogLike;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\BlogResource;
use App\Http\Requests\StoreBlogRequest;
use App\Models\Section;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $blog->loadCount(['comments', 'likes']);
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

        $blog->loadCount(['comments', 'likes']);
        $blog->load(['tags','sections']);

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
