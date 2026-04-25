<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Tag;
use App\Services\RecommendationCacheService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class TagController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly RecommendationCacheService $recommendationCacheService)
    {
    }

    public function index(){
        $tags=Tag::all();
        return $this->successResponse(TagResource::collection($tags), 'Tags retrieved successfully.');
    }

    public function updatePost(Request $request, $id)
    {
        $atts = $request->validate([
            'tags' => 'required|array',
            'tags.*' => 'exists:tags,id',
        ]);

        $post = Post::find($id);
        if (! $post) {
            return $this->notFoundResponse('Post not found');
        }

        if ($post->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to update this post tags');
        }

        $post->tags()->sync($atts['tags']);

        return $this->successResponse(null, 'Tags updated successfully');
    }

    public function updateProfile(Request $request, $id)
    {
        $atts = $request->validate([
            'tags' => 'required|array',
            'tags.*' => 'exists:tags,id',
        ]);

        $profile = Profile::find($id);
        if (! $profile) {
            return $this->notFoundResponse('Profile not found');
        }

        if ($profile->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to update this profile tags');
        }

        $profile->tags()->sync($atts['tags']);
        $this->recommendationCacheService->bumpUserVersion($request->user()->id);

        return $this->successResponse(null, 'Tags updated successfully');
    }

    public function survy(Request $request)
    {
        $atts = $request->validate([
            'tags' => 'required|array|min:1',
            'tags.*' => 'exists:tags,id',
        ]);

        $profile = Profile::where('user_id', $request->user()->id)->first();
        if (! $profile) {
            return $this->notFoundResponse('Profile not found');
        }

        $profile->tags()->sync($atts['tags']);
        $this->recommendationCacheService->bumpUserVersion($request->user()->id);

        return $this->successResponse(null, 'Tags updated successfully');
    }
}
