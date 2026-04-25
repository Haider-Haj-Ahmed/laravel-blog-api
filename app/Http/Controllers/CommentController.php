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
use App\Events\CommentUnhighlighted;
use App\Notifications\HighlightedCommentUpdatedNotification;
use App\Events\PostCommented;
use App\Events\BlogCommented;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CommentController extends Controller
{
    use MentionTrait, ApiResponseTrait;

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly RecommendationCacheService $recommendationCacheService
    ) {}

    public function indexByPost(Request $request, $postId)
    {
        $post = Post::find($postId);
        if (!$post) {
            return $this->notFoundResponse('this post is not found');
        }
        if (!$post->is_published) {
            return $this->unauthorizedResponse('you cannot access this post');
        }
        $highlighted = Activity::where('action', 'comment_highlighted')->where('subject_id', $postId)->where('subject_type', 'post')->latest()->first();
        $comments = Comment::where('post_id', $postId)
            ->with(['user', 'mentions'])
            ->latest()
            ->paginate(15);
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
        //find for post and blog
        if (isset($atts['post_id'])) {
            $post = Post::find($atts['post_id']);
            if (!$post) {
                return $this->notFoundResponse('this post is not found');
            }
            if (!$post->is_published) {
                return $this->unauthorizedResponse('you cannot comment on this post');
            }
        } elseif (isset($atts['blog_id'])) {
            $blog = Blog::find($atts['blog_id']);
            if (!$blog) {
                return $this->notFoundResponse('this blog is not found');
            }
            if (!$blog->is_published) {
                return $this->unauthorizedResponse('you cannot comment on this blog');
            }
        }
        $comment = Comment::create([
            'body' => $atts['body'],
            'user_id' => $request->user()->id,
            'post_id' => $atts['post_id'] ?? null,
            'blog_id' => $atts['blog_id'] ?? null,
            'code' => $atts['code'] ?? null,
            'code_language' => $atts['code_language'] ?? null,
            'parent_id' => $atts['parent_id'] ?? null,
        ]);
        if(!$comment){
            return $this->errorResponse('Failed to create comment', 500);
        }
        $this->refreshSubjectCommentCounter($comment->post_id, $comment->blog_id);
        $this->handleMentions($comment);
        if (isset($atts['code'])) {
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
        if($comment->post_id){
            $post = Post::find($comment->post_id);
            if (!$post) {
                return $this->notFoundResponse('Associated post not found');
            }
            if (!$post->is_published) {
                return $this->unauthorizedResponse('you cannot update comment on this post');
            }
        }elseif($comment->blog_id){
            $blog = Blog::find($comment->blog_id);
            if (!$blog) {
                return $this->notFoundResponse('Associated Blog not found');
            }
            if(!$blog->is_published){
                return $this->unauthorizedResponse('you cannot update comment on this blog');
            }
        }
        // تحقق إنو المستخدم الحالي هو صاحب التعليق
        if ($comment->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to update this comment');
        }


        $atts = $request->validate([
            'body' => 'sometimes|string',
            'code' => 'nullable|string',
            'code_language' => 'nullable|string|max:50',
        ]);
        preg_match_all('/@([\w\-]+)/', $comment->body, $matches);

        $oldUsernames = $matches[1] ?? [];
        $shouldMarkModified = $this->commentContentChanged($comment, $atts);
        $b=$comment->update([
            'body' => $atts['body'] ?? $comment->body,
            'code' => $atts['code'] ?? $comment->code,
            'code_language' => array_key_exists('code_language', $atts) ? $atts['code_language'] : $comment->code_language,
        ]);
        if(!$b){
            return $this->errorResponse('Failed to update comment', 500);
        }

        if ($shouldMarkModified) {
            $comment->forceFill(['is_modified' => true])->save();
        }

        $this->handleMentions($comment, $oldUsernames);
        if (isset($atts['code'])) {
            AnalyzeCommentCode::dispatch($comment);
        }

        if ($shouldMarkModified && $comment->is_highlighted) {
            $this->notifySubjectOwnerOfHighlightedCommentEdit($comment, $request->user());
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
        if($comment->post_id){
            $post = Post::find($comment->post_id);
            if (!$post) {
                return $this->notFoundResponse('Associated post not found');
            }
            if (!$post->is_published) {
                return $this->unauthorizedResponse('you cannot update comment on this post');
            }
        }elseif($comment->blog_id){
            $blog = Blog::find($comment->blog_id);
            if (!$blog) {
                return $this->notFoundResponse('Associated Blog not found');
            }
            if(!$blog->is_published){
                return $this->unauthorizedResponse('you cannot update comment on this blog');
            }
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
        if($comment->post_id){
            $post = Post::find($comment->post_id);
            if (!$post) {
                return $this->notFoundResponse('Associated post not found');
            }
            if (!$post->is_published) {
                return $this->unauthorizedResponse('you cannot update comment on this post');
            }
        }elseif($comment->blog_id){
            $blog = Blog::find($comment->blog_id);
            if (!$blog) {
                return $this->notFoundResponse('Associated Blog not found');
            }
            if(!$blog->is_published){
                return $this->unauthorizedResponse('you cannot update comment on this blog');
            }
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

        $cooldownKey = "comment:highlight-toggle:{$request->user()->id}:{$comment->id}";
        $cooldownSeconds = 3;

        if (! Cache::add($cooldownKey, true, now()->addSeconds($cooldownSeconds))) {
            return $this->errorResponse('Please wait a moment before toggling highlight again.', 429);
        }

        $comment->is_highlighted = ! (bool) $comment->is_highlighted;
        $comment->save();

        if ($comment->is_highlighted) {
            CommentHighlighted::dispatch($comment, $request->user());
        } else {
            CommentUnhighlighted::dispatch($comment, $request->user());
        }

        $comment->load(['user', 'mentions']);
        $comment->setAttribute(
            'is_liked_by_user',
            $comment->likes()->where('user_id', $request->user()->id)->exists()
        );

        return $this->successResponse(
            new CommentResource($comment),
            $comment->is_highlighted ? 'Comment highlighted successfully' : 'Comment unhighlighted successfully'
        );
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

        DB::transaction(function () use ($comment) {
            $postId = $comment->post_id;
            $blogId = $comment->blog_id;

            $this->activityService->purgeActivitiesForDeletedComment($comment);
            $comment->delete();
            $this->decrementSubjectCommentCounter($postId, $blogId);
        });

        return $this->successResponse(null, 'Comment deleted successfully');
    }

    private function decrementSubjectCommentCounter(?int $postId, ?int $blogId): void
    {
        if ($postId) {
            Post::query()
                ->whereKey($postId)
                ->where('comments_count', '>', 0)
                ->decrement('comments_count');
        }

        if ($blogId) {
            Blog::query()
                ->whereKey($blogId)
                ->where('comments_count', '>', 0)
                ->decrement('comments_count');
        }
    }

    private function commentContentChanged(Comment $comment, array $validated): bool
    {
        foreach (['body', 'code', 'code_language'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($comment->{$field} !== $validated[$field]) {
                return true;
            }
        }

        return false;
    }

    private function notifySubjectOwnerOfHighlightedCommentEdit(Comment $comment, User $editor): void
    {
        $subjectOwner = $comment->post?->user ?? $comment->blog?->user;

        if (! $subjectOwner || $subjectOwner->id === $editor->id) {
            return;
        }

        $subjectOwner->notify(new HighlightedCommentUpdatedNotification($comment, $editor));
    }


}
