<?php

namespace App\Http\Resources;

use App\Models\User;
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
        return [
            'id' => $this->id,
            'body' => $this->body,
            'code'=>$this->code,
            'code_label'=>$this->code_label,
            'parent_id'=>$this->parent_id,
            'user_id' =>$this->user_id,
            'user_name'=>User::where('id',$this->user_id)->value('username'), 
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
