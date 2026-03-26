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

        return [
            'id' => $this->id,
            'body' => $this->body,
            'type' => $type,
            'code'=>$this->code,
            'code_label'=>$this->code_label,
            'parent_id'=>$this->parent_id,
            'user_id' =>$this->user_id,
            'post_id' => $this->post_id,
            'blog_id' => $this->blog_id,
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
