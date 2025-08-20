<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Resources\CommentResource;
use App\Traits\MentionTrait;

class CommentController extends Controller
{
    use MentionTrait;
    public function index($postId)
    {
        $comments = Comment::where('post_id', $postId)->with('user', 'mentions')->get();

        if ($comments->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No comments found for this post',
                'data' => [],
            ], 200);
        }

        return CommentResource::collection($comments);
    }

    public function store(Request $request, $postId)
    {
        $request->validate([
            'body' => 'required|string',
            'post_id' => 'required|exists:posts,id',
        ]);

        $comment = Comment::create([
            'body' => $request->body,
            'user_id' => Auth::id(),  // استخدام Auth بدل auth()
            'post_id' => $postId,
        ]);

        $this->handleMentions($comment);

        return new CommentResource(
            $comment->load(['user', 'mentions'])
        );

    }

    public function update(Request $request, $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        // تحقق إنو المستخدم الحالي هو صاحب التعليق
        if ($comment->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'body' => 'required|string',
        ]);

        $comment->update([
            'body' => $request->body,
        ]);

        return response()->json($comment);
    }

    public function destroy($id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        if ($comment->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }


}
