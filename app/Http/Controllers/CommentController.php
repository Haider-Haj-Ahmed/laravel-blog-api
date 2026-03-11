<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Resources\CommentResource;
use App\Jobs\AnalyzeCommentCode;
use App\Traits\MentionTrait;
use App\Traits\ApiResponseTrait;

class CommentController extends Controller
{
    use MentionTrait, ApiResponseTrait;
    public function index($postId)
    {
        $comments = Comment::where('post_id', $postId)
            ->with(['user', 'mentions'])
            ->latest()
            ->paginate(15);

        if ($comments->isEmpty()) {
            return $this->successResponse([], 'No comments found for this post');
        }

        return $this->paginatedResponse($comments, 'Comments retrieved successfully');
    }

    public function store(StoreCommentRequest $request)
    {
        $atts=$request->validated();

        $comment = Comment::create([
            'body' => $atts->body,
            'user_id' => $request->user()->id,
            'post_id' => $atts->post_id,
            'code' => $atts->code ?? null,
            'parent_id' => $atts->parent_id ?? null,
        ]);

        $this->handleMentions($comment);
        if(isset($atts->code)){
            AnalyzeCommentCode::dispatch($comment);
        }
        // Load relationships and return resource
        $comment->load(['user', 'mentions']);

        return $this->createdResponse(
            CommentResource::make($comment),
            'Comment created successfully'
        );
    }

    public function update(Request $request, $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }

        // تحقق إنو المستخدم الحالي هو صاحب التعليق
        if ($comment->user_id !== Auth::id()) {
            return $this->forbiddenResponse('You are not authorized to update this comment');
        }

        $request->validate([
            'body' => 'required|string',
        ]);

        $comment->update([
            'body' => $request->body,
        ]);

        // Load relationships and return resource
        $comment->load(['user', 'mentions']);

        return $this->successResponse(
            new CommentResource($comment),
            'Comment updated successfully'
        );
    }

    public function destroy($id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }

        if ($comment->user_id !== Auth::id()) {
            return $this->forbiddenResponse('You are not authorized to delete this comment');
        }

        $comment->delete();

        return $this->successResponse(null, 'Comment deleted successfully');
    }


}
