<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSummaryResource;
use App\Models\Blog;
use App\Models\Post;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    use ApiResponseTrait;

    public function search(Request $request){
        $atts=$request->validate([
              'query'=>'string|required',
              'tab'=>'sometimes|string|in:posts,blogs,users',
              'tags'=>'array',
              'tags.*'=>'exists:tags,id',
              'page'=>'sometimes|integer|min:1',
        ]);
        if(!isset($atts['tab'])){
            $atts['tab']='posts';
        }
        $usersQuery = User::where(function ($q) use ($atts) {
            $q->where('name', 'like', '%'.$atts['query'].'%')
                ->orWhere('username', 'like', '%'.$atts['query'].'%')
                ->orWhereHas('profile', function ($q2) use ($atts) {
                    $q2->where('bio', 'like', '%'.$atts['query'].'%');
                })->latest();
        });

        $postsQuery = Post::where('is_published', true)
            ->where('title', 'like', '%'.$atts['query'].'%')
            ->latest();
        $blogsQuery = Blog::where('is_published', true)
            ->where('title', 'like', '%'.$atts['query'].'%')
            ->latest();

        if (!empty($atts['tags'])) {
            $tags = $atts['tags'];

            $postsQuery->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn(DB::raw('tags.id'), $tags);
            });

            $blogsQuery->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn(DB::raw('tags.id'), $tags);
            });

            $usersQuery->whereHas('profile.tags', function ($q) use ($tags) {
                $q->whereIn(DB::raw('tags.id'), $tags);
            });
        }

        $perPage = 2;
        $page = $atts['page'] ?? 1;
        // $offset = ($page - 1) * $perPage;

        // $users = $usersQuery->with('profile')->skip($offset)->take($perPage)->get();
        // $posts = $postsQuery->with('tags')->skip($offset)->take($perPage)->get();
        // $blogs = $blogsQuery->with('tags')->skip($offset)->take($perPage)->get();
        if($atts['tab']=='users'){
            $users = $usersQuery->with('profile')->skip(0)->take($perPage*$page)->get();
            return $this->successResponse(UserSummaryResource::collection($users), 'Users retrieved successfully');
        }elseif($atts['tab']=='posts'){
            $posts = $postsQuery->with('tags')->skip(0)->take($perPage*$page)->get();
            return $this->successResponse($posts, 'Posts retrieved successfully');
        }elseif($atts['tab']=='blogs'){
            $blogs = $blogsQuery->with('tags')->skip(0)->take($perPage*$page)->get();
            return $this->successResponse($blogs, 'Blogs retrieved successfully');
        }

        return $this->errorResponse('Unsupported search tab', 400);
    }
}
