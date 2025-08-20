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
        return [
            'id' => $this->id,
            'body' => $this->body,
            'user' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
            ],
            'mentions' => $this->mentions->map(function ($user) {
                return [
                    'username' => $user->username,
                    'profile_url' => url("/api/users/{$user->username}")
                ];
            }),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }

}
