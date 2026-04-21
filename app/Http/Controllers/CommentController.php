<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comment;
use App\Http\Resources\CommentResource;
use App\Jobs\AnalyzeCommentCode;
use App\Traits\MentionTrait;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use App\Models\Blog;
use App\Models\Post;
use App\Services\ActivityService;
use App\Services\RecommendationCacheService;
use App\Events\CommentLiked;
use App\Events\CommentDisliked;
use App\Events\CommentHighlighted;
use App\Events\PostCommented;
use App\Events\BlogCommented;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CommentController extends Controller
{
    use MentionTrait, ApiResponseTrait;

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly RecommendationCacheService $recommendationCacheService
    ) {}

    public function index($postId)
    {
        $viewerId = auth('sanctum')->id();
        $commentsQuery = Comment::where('post_id', $postId)
            ->with(['user', 'mentions']);

        if ($viewerId) {
            $commentsQuery->withExists([
                'likes as is_liked_by_user' => fn ($query) => $query->where('user_id', $viewerId),
            ]);
        }

        $comments = $commentsQuery->latest()->paginate(15);

        if ($comments->isEmpty()) {
            return $this->successResponse([], 'No comments found for this post');
        }

        return $this->paginatedResponse(
            CommentResource::collection($comments),
            'Comments retrieved successfully'
        );
    }

    public function indexByBlog($blogId)
    {
        $viewerId = auth('sanctum')->id();
        $commentsQuery = Comment::where('blog_id', $blogId)
            ->with(['user', 'mentions']);

        if ($viewerId) {
            $commentsQuery->withExists([
                'likes as is_liked_by_user' => fn ($query) => $query->where('user_id', $viewerId),
            ]);
        }

        $comments = $commentsQuery->latest()->paginate(15);

        if ($comments->isEmpty()) {
            return $this->successResponse([], 'No comments found for this blog');
        }

        return $this->paginatedResponse(
            CommentResource::collection($comments),
            'Comments retrieved successfully'
        );
    }

    public function show($id)
    {
        $viewerId = auth('sanctum')->id();
        $commentQuery = Comment::with(['user', 'mentions']);

        if ($viewerId) {
            $commentQuery->withExists([
                'likes as is_liked_by_user' => fn ($query) => $query->where('user_id', $viewerId),
            ]);
        }

        $comment = $commentQuery->find($id);

        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }

        return $this->successResponse(new CommentResource($comment), 'Comment retrieved successfully');
    }

    public function store(Request $request)
    {
        $atts = $request->validate([
            'body' => 'required|string',
            'post_id' => 'nullable|exists:posts,id|required_without:blog_id|prohibited_with:blog_id',
            'blog_id' => 'nullable|exists:blogs,id|required_without:post_id|prohibited_with:post_id',
            'code' => 'nullable|string',
            'code_language' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $comment = Comment::create([
            'body' => $atts['body'],
            'user_id' => $request->user()->id,
            'post_id' => $atts['post_id'] ?? null,
            'blog_id' => $atts['blog_id'] ?? null,
            'code' => $atts['code'] ?? null,
            'code_language' => $atts['code_language'] ?? null,
            'parent_id' => $atts['parent_id'] ?? null,
        ]);

        $this->handleMentions($comment);
        if(isset($atts['code'])){
            AnalyzeCommentCode::dispatch($comment);
        }

        if ($comment->post_id) {
            $this->recommendationCacheService->bumpUserVersion($request->user()->id);
        }

        //Load relationships and return resource
        $comment->load(['user', 'mentions']);
        $comment->setAttribute('is_liked_by_user', false);

        if ($comment->post_id) {
            $post = Post::find($comment->post_id);
            if ($post) {
                PostCommented::dispatch($post, $comment, $request->user());
            }
        }

        if ($comment->blog_id) {
            $blog = Blog::find($comment->blog_id);
            if ($blog) {
                BlogCommented::dispatch($blog, $comment, $request->user());
            }
        }

        return $this->createdResponse(
            CommentResource::make($comment),
            'Comment created successfully'
        );
    }

    public function storeForBlog(Request $request, Blog $blog)
    {
        $request->merge(['blog_id' => $blog->id]);

        return $this->store($request);
    }

    public function update(Request $request, $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }

        // تحقق إنو المستخدم الحالي هو صاحب التعليق
        if ($comment->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to update this comment');
        }

        $atts = $request->validate([
            'body' => 'nullable|string',
            'code' => 'nullable|string',
            'code_language' => 'nullable|string|max:50',
        ]);
        preg_match_all('/@([\w\-]+)/', $comment->body, $matches);

        $oldUsernames = $matches[1] ?? [];
        $comment->update([
            'body' => $atts['body'] ?? $comment->body,
            'code' => $atts['code'] ?? $comment->code,
            'code_language' => array_key_exists('code_language', $atts) ? $atts['code_language'] : $comment->code_language,
        ]);

        $this->handleMentions($comment, $oldUsernames);
        if (isset($atts['code'])) {
            AnalyzeCommentCode::dispatch($comment);
        }

        // Load relationships and return resource
        $comment->load(['user', 'mentions']);
        $comment->setAttribute(
            'is_liked_by_user',
            $comment->likes()->where('user_id', $request->user()->id)->exists()
        );

        return $this->successResponse(
            new CommentResource($comment),
            'Comment updated successfully'
        );
    }
    public function like(Request $request, $id)
    {
        $comment = Comment::find($id);
        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }
        $statusL = $comment->likes()->where('user_id', $request->user()->id)->exists();
        $statusD = $comment->dislikes()->where('user_id', $request->user()->id)->exists();
        if (!$statusL) {
            if (!$statusD) {
                $comment->likes()->attach($request->user()->id, ['is_like' => true]);
                $comment->likes++;
                $comment->save();
                CommentLiked::dispatch($comment, $request->user());
            } else {
                DB::table('comment_user_likes')->where('comment_id', $comment->id)->where('user_id', $request->user()->id)->update(['is_like' => true]);
                $comment->dislikes--;
                $comment->likes++;
                $comment->save();
                CommentLiked::dispatch($comment, $request->user());
            }
        } else {
            $comment->likes()->detach($request->user()->id);
            $comment->likes--;
            $comment->save();
        }

        if ($comment->post_id) {
            $this->recommendationCacheService->bumpUserVersion($request->user()->id);
        }

        $isLikedByUser = $comment->likes()->where('user_id', $request->user()->id)->exists();

        return $this->successResponse([
            'likes' => $comment->likes,
            'dislikes' => $comment->dislikes,
            'is_liked_by_user' => $isLikedByUser,
        ], 'Like status updated');
    }
    public function dislike(Request $request, $id)
    {
        $comment = Comment::find($id);
        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }
        $statusL = $comment->likes()->where('user_id', $request->user()->id)->exists();
        $statusD = $comment->dislikes()->where('user_id', $request->user()->id)->exists();
        if (!$statusD) {
            if (!$statusL) {
                $comment->dislikes()->attach($request->user()->id, ['is_like' => false]);
                $comment->dislikes++;
                $comment->save();
                CommentDisliked::dispatch($comment, $request->user());
            } else {
                DB::table('comment_user_likes')->where('comment_id', $comment->id)->where('user_id', $request->user()->id)->update(['is_like' => false]);
                $comment->likes--;
                $comment->dislikes++;
                $comment->save();
                CommentDisliked::dispatch($comment, $request->user());
            }
        } else {
            $comment->dislikes()->detach($request->user()->id);
            $comment->dislikes--;
            $comment->save();
        }

        if ($comment->post_id) {
            $this->recommendationCacheService->bumpUserVersion($request->user()->id);
        }

        $isLikedByUser = $comment->likes()->where('user_id', $request->user()->id)->exists();

        return $this->successResponse([
            'likes' => $comment->likes,
            'dislikes' => $comment->dislikes,
            'is_liked_by_user' => $isLikedByUser,
        ], 'Dislike status updated');
    }

    public function getChildren(Request $request, $id)
    {
        $comment = Comment::find($id);
        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }
        $perPage = 3;
        $page = $request->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $allCount = $comment->children()->count();
        $allchildren = [];
        $viewerId = auth('sanctum')->id();
        for ($i = $page; $i > 0; $i--) {
            $childrenQuery = $comment->children()->latest();

            if ($viewerId) {
                $childrenQuery->withExists([
                    'likes as is_liked_by_user' => fn ($query) => $query->where('user_id', $viewerId),
                ]);
            }

            $children = $childrenQuery->paginate($perPage, ['*'], 'page', $i);
            $allchildren = array_merge($allchildren, $children->items());
        }

        return $this->successResponse([
            'children' => CommentResource::collection(collect($allchildren)),
            'total_pages' => (int) ceil($allCount / $perPage),
        ], 'Child comments retrieved successfully');
    }

    public function highlight(Request $request, $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }

        $ownerId = $comment->post?->user_id ?? $comment->blog?->user_id;

        if (!$ownerId || $ownerId !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to highlight this comment');
        }

        // Dispatch the highlight event
        CommentHighlighted::dispatch($comment, $request->user());

        return $this->successResponse(null, 'Comment highlighted successfully');
    }
    public function suggest(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:50'],
        ]);

        $search = $request->string('q')->trim()->lower();
        $authUser = $request->user();

        // Cache both lists — note pluck('id') since these are belongsToMany returning User models
        $followingIds = Cache::remember(
            "user:{$authUser->id}:following_ids",
            now()->addHour(),
            fn() => $authUser->following()->pluck('users.id')
        );

        $followerIds = Cache::remember(
            "user:{$authUser->id}:follower_ids",
            now()->addHour(),
            fn() => $authUser->followers()->pluck('users.id')
        );

        $knownIds = $followingIds->merge($followerIds)->unique();

        // First: search within known users
        $knownUsers = User::query()
            ->with('profile:user_id,avatar')
            ->whereIn('id', $knownIds)
            ->where('username', 'like', $search . '%')
            ->select('id', 'name', 'username')
            ->limit(5)
            ->get();

        $results = collect($knownUsers);

        // Second: fill remaining slots from everyone else
        if ($results->count() < 5) {
            $excludeIds = $knownIds->push($authUser->id);

            $otherUsers = User::query()
                ->with('profile:user_id,avatar')
                ->whereNotIn('id', $excludeIds)
                ->where('username', 'like', $search . '%')
                ->select('id', 'name', 'username')
                ->limit(5 - $results->count())
                ->get();

            $results = $results->merge($otherUsers);
        }

        $formatted = $results->map(fn($user) => [
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'avatar'   => $user->profile?->avatar,
        ]);

        return $this->successResponse($formatted, 'Suggestions retrieved successfully');
    }

    public function destroy(Request $request, $id)
    {
        $comment = Comment::find($id);

        if (! $comment) {
            return $this->notFoundResponse('Comment not found');
        }

        if ($comment->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to delete this comment');
        }

        $comment->delete();

        return $this->successResponse(null, 'Comment deleted successfully');
    }


}
