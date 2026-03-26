<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\BlogResource;
use App\Http\Requests\StoreBlogRequest;

class BlogController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of published blogs.
     */
    public function index()
    {
        $blogs = Blog::with('user')
            ->where('is_published', true)
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
        // $this->authorize('view', $blog);

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
}
