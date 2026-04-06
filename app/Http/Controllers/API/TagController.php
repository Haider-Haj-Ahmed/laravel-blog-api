<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Tag;
use App\Services\RecommendationCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TagController extends Controller
{
    public function __construct(private readonly RecommendationCacheService $recommendationCacheService)
    {
    }

    public function index(){
        $tags=Tag::all();
        return response()->json(['tags'=>$tags]);
    }
    public function updatePost(Request $request,$id){
        $atts=$request->validate([
            'tags'=>'array',
            'tags.*'=>'exists:tags,id'
        ]);
        $post=Post::find($id);
        if(!$post){
            return response()->json(['message'=>'Post not found'],404);
        }
        if($post->user_id!=$request->user()->id){
            return response()->json(['message'=>'Unauthorized'],403);
        }
        $post->tags()->sync($atts['tags']);
        return response()->json(['message'=>'Tags updated successfully']);

    }
    public function updateProfile(Request $request,$id){
        $atts=$request->validate([
            'tags'=>'array',
            'tags.*'=>'exists:tags,id'
        ]);
        $profile=Profile::find($id);
        if(!$profile){
            return response()->json(['message'=>'Profile not found'],404);
        }
        if($profile->user_id!=$request->user()->id){
            return response()->json(['message'=>'Unauthorized'],403);
        }
        $profile->tags()->sync($atts['tags']);
        $this->recommendationCacheService->bumpUserVersion($request->user()->id);
        return response()->json(['message'=>'Tags updated successfully']);

    }
    public function survy(Request $request){
        $atts=$request->validate([
            'tags'=>'array|min:1',
            'tags.*'=>'exists:tags,id'
        ]);
        Log::error($request->user()->id);
        $profile=Profile::where('user_id',$request->user()->id)->first();
        if(!$profile){
            return response()->json(['message'=>'Profile not found'],404);
        }
        $profile->tags()->sync($atts['tags']);
        $this->recommendationCacheService->bumpUserVersion($request->user()->id);
        return response()->json(['message'=>'Tags updated successfully']);
    }
}
