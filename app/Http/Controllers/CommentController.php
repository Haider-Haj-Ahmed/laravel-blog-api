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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\CommentLiked;
use App\Events\CommentDisliked;
use App\Events\CommentHighlighted;

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

        return $this->paginatedResponse(
            CommentResource::collection($comments),
            'Comments retrieved successfully'
        );
    }
    public function show($id)
    {
        $comment = Comment::with(['user', 'mentions'])->find($id);

        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }

        return $this->successResponse(new CommentResource($comment), 'Comment retrieved successfully');
    }

    public function store(Request $request)
    {
        $atts=$request->validate([
            'body' => 'required|string',
            'post_id' => 'required|exists:posts,id',
            'code'=>'nullable|string',
            'parent_id'=>'nullable|exists:comments,id',
        ]);
        Log::error($atts);
        $comment = Comment::create([
            'body' => $atts['body'],
            'user_id' => $request->user()->id,
            'post_id' => $atts['post_id'],
            'code' => $atts['code'] ?? null,
            'parent_id' => $atts['parent_id'] ?? null,
        ]);

        $this->handleMentions($comment);
        // if(isset($atts['code'])){
        //     AnalyzeCommentCode::dispatch($comment);
        // }
        //Load relationships and return resource
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
        if ($comment->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to update this comment');
        }

        $atts=$request->validate([
            'body' => 'nullable|string',
            'code'=>'nullable|string',
        ]);
        preg_match_all('/@([\w\-]+)/', $comment->body, $matches);

        $oldUsernames = $matches[1] ?? [];
        $comment->update([
            'body' => $atts['body'] ?? $comment->body,
            'code' => $atts['code'] ?? $comment->code
        ]);

        $this->handleMentions($comment,$oldUsernames);
        if(isset($atts['code'])){
            AnalyzeCommentCode::dispatch($comment);
        }

        // Load relationships and return resource
        $comment->load(['user', 'mentions']);

        return $this->successResponse(
            new CommentResource($comment),
            'Comment updated successfully'
        );
    }
    public function like(Request $request,$id){
        $comment=Comment::find($id);
        if(!$comment){
            return response()->json(['message'=>'Comment not found'],404);
        }
        $statusL=$comment->likes()->where('user_id', $request->user()->id)->exists();
        $statusD=$comment->dislikes()->where('user_id', $request->user()->id)->exists();
        if(!$statusL){
            if(!$statusD){
                $comment->likes()->attach($request->user()->id,['is_like'=>true]);
                $comment->likes++;
                $comment->save();
                CommentLiked::dispatch($comment);
            }else{
                DB::table('comment_user_likes')->where('comment_id', $comment->id)->where('user_id', $request->user()->id)->update(['is_like' => true]);
                $comment->dislikes--;
                $comment->likes++;
                $comment->save();
                CommentLiked::dispatch($comment);
            }
        }else{
            $comment->likes()->detach($request->user()->id);
            $comment->likes--;
            $comment->save();
        }
        return response()->json(['message'=>'Like status updated','likes'=>$comment->likes,'dislikes'=>$comment->dislikes],200);
    }
    public function dislike(Request $request,$id){
        $comment=Comment::find($id);
        if(!$comment){
            return response()->json(['message'=>'Comment not found'],404);
        }
        $statusL=$comment->likes()->where('user_id', $request->user()->id)->exists();
        $statusD=$comment->dislikes()->where('user_id', $request->user()->id)->exists();
        if(!$statusD){
            if(!$statusL){
                $comment->dislikes()->attach($request->user()->id,['is_like'=>false]);
                $comment->dislikes++;
                $comment->save();
                CommentDisliked::dispatch($comment);
            }else{
                DB::table('comment_user_likes')->where('comment_id', $comment->id)->where('user_id', $request->user()->id)->update(['is_like' => false]);
                $comment->likes--;
                $comment->dislikes++;
                $comment->save();
                CommentDisliked::dispatch($comment);
            }
        }else{
            $comment->dislikes()->detach($request->user()->id);
            $comment->dislikes--;
            $comment->save();
        }
        return response()->json(['message'=>'Dislike status updated','likes'=>$comment->likes,'dislikes'=>$comment->dislikes],200);
    }

    public function getChildren(Request $request,$id){
        $comment=Comment::find($id);
        if(!$comment){
            return response()->json(['message'=>'Comment Not Found'],404);
        }
        $perPage = 3;
        $page = $request->get('page', 1);
        if($page < 1){
            $page=1;
        }
        $allCount=$comment->children()->count();
        $allchildren=[];
        for ($i=$page;$i>0;$i--){
            $children = $comment->children()
            ->latest()
            ->paginate($perPage, ['*'], 'page', $i);
            $allchildren=array_merge($allchildren,$children->items());
        }
        
        return response()->json(['data'=>$allchildren,'message'=>'Child comments retrieved successfully','num of total pages'=>ceil($allCount/$perPage)],200);
    }

    public function highlight(Request $request, $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return $this->notFoundResponse('Comment not found');
        }

        // Check if the authenticated user is the author of the post
        if ($comment->post->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to highlight this comment');
        }

        // Dispatch the highlight event
        CommentHighlighted::dispatch($comment);

        return $this->successResponse(null, 'Comment highlighted successfully');
    }

    // public function destroy($id)
    // {
    //     $comment = Comment::find($id);

    //     if (!$comment) {
    //         return $this->notFoundResponse('Comment not found');
    //     }

    //     if ($comment->user_id !== Auth::id()) {
    //         return $this->forbiddenResponse('You are not authorized to delete this comment');
    //     }

    //     $comment->delete();

    //     return $this->successResponse(null, 'Comment deleted successfully');
    // }


}
