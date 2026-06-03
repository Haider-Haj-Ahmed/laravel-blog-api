<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\Blog;
use App\Models\Post;
use App\Models\User;
use App\Services\BlockedUserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly BlockedUserService $blockedUserService) {}

    public function search(Request $request)
    {
        $atts = $request->validate([
            'query' => 'string|required',
            'tab' => 'sometimes|string|in:posts,blogs,users',
            'tags' => 'array',
            'tags.*' => 'exists:tags,id',
            'page' => 'sometimes|integer|min:1',
        ]);
        if (! isset($atts['tab'])) {
            $atts['tab'] = 'posts';
        }
        $usersQuery = User::where(function ($q) use ($atts) {
            $q->where('name', 'like', '%'.$atts['query'].'%')
                ->orWhere('username', 'like', '%'.$atts['query'].'%')
                ->orWhereHas('profile', function ($q2) use ($atts) {
                    $q2->where('bio', 'like', '%'.$atts['query'].'%');
                })->latest();
        });

        $viewer = auth('sanctum')->user();
        $usersQuery->where(function ($q) use ($viewer) {
            $q->whereHas('profile', function ($profileQuery) {
                $profileQuery->where(function ($settingsQuery) {
                    $settingsQuery
                        ->whereNull('settings->privacy->profile_discoverable')
                        ->orWhere('settings->privacy->profile_discoverable', true);
                });
            });

            if ($viewer) {
                $q->orWhere('users.id', $viewer->id);
            }
        });

        $postsQuery = Post::where('is_published', true)
            ->where('title', 'like', '%'.$atts['query'].'%')
            ->latest();
        $blogsQuery = Blog::where('is_published', true)
            ->where('title', 'like', '%'.$atts['query'].'%')
            ->latest();

        if (! empty($atts['tags'])) {
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

        if ($viewer) {
            $blockedIds = $this->blockedUserService->blockedUserIds($viewer);
            if ($blockedIds !== []) {
                $postsQuery->whereNotIn('user_id', $blockedIds);
                $blogsQuery->whereNotIn('user_id', $blockedIds);
                $usersQuery->whereNotIn('users.id', $blockedIds);
            }
        }

        $perPage = 2;
        $page = $atts['page'] ?? 1;
        // $offset = ($page - 1) * $perPage;

        // $users = $usersQuery->with('profile')->skip($offset)->take($perPage)->get();
        // $posts = $postsQuery->with('tags')->skip($offset)->take($perPage)->get();
        // $blogs = $blogsQuery->with('tags')->skip($offset)->take($perPage)->get();
        if ($atts['tab'] == 'users') {
            $users = $usersQuery->with('profile')->skip(0)->take($perPage * $page)->get();

            return $this->successResponse(UserSummaryResource::collection($users), 'Users retrieved successfully');
        } elseif ($atts['tab'] == 'posts') {
            $posts = $postsQuery->with(['tags', 'photos', 'user'])->skip(0)->take($perPage * $page)->get();

            return $this->successResponse(PostResource::collection($posts), 'Posts retrieved successfully');
        } elseif ($atts['tab'] == 'blogs') {
            $blogs = $blogsQuery->with(['tags', 'sections', 'user'])->skip(0)->take($perPage * $page)->get();

            return $this->successResponse(BlogResource::collection($blogs), 'Blogs retrieved successfully');
        }

        return $this->errorResponse('Unsupported search tab', 400);
    }
}
