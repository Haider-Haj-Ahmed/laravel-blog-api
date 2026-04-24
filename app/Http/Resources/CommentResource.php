<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    // App\Http\Resources\CommentResource
    public function toArray($request)
    {
        $hasCode = !empty($this->code);
        $type = $hasCode ? 'text_code' : 'text';
        $viewer = auth('sanctum')->user() ?? $request->user();
        $isLikedByUser = isset($this->is_liked_by_user)
            ? (bool) $this->is_liked_by_user
            : ($viewer ? $this->isLikedBy($viewer) : false);

        return [
            'id' => $this->id,
            'body' => $this->body,
            'type' => $type,
            'code'=>$this->code,
            'code_language' => $this->code_language,
            'parent_id'=>$this->parent_id,
            'has_childrens'=>$this->has_childrens(),
            'user_id' =>$this->user_id,
            'post_id' => $this->post_id,
            'blog_id' => $this->blog_id,
            'is_modified' => (bool) ($this->is_modified ?? false),
            'is_highlighted' => (bool) ($this->is_highlighted ?? false),
            'is_liked_by_user' => $isLikedByUser,
            'user_name'=> $this->whenLoaded('user', fn () => $this->user->username), 
            'mentions' => $this->whenLoaded('mentions', function () {
                return $this->mentions->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'username' => $user->username,
                        'profile_url' => url("/api/users/{$user->username}")
                    ];
                });
            }),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }

}
