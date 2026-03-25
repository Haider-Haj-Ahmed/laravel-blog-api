<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $hasCode = !empty($this->code);
        $hasPhoto = !empty($this->photo);

        $type = 'text';
        if ($hasCode && $hasPhoto) {
            $type = 'text_code_photo';
        } elseif ($hasCode) {
            $type = 'text_code';
        } elseif ($hasPhoto) {
            $type = 'text_photo';
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'code' => $this->code,
            'photo_url' => $hasPhoto ? asset("storage/post_photos/{$this->photo}") : null,
            'type' => $type,
            'is_published' => $this->is_published,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'name' => $this->user->name,
                    'avatar_url' => $this->user->profile?->avatar ? asset("storage/avatars/{$this->user->profile->avatar}") : asset('images/default-avatar.png'),
                    'badge' => $this->user->profile?->badge ?? 'junior',
                ];
            }),
            'comments_count' => $this->when(isset($this->comments_count), $this->comments_count),
            'likes_count' => $this->when(isset($this->likes_count), $this->likes_count),
            'is_liked_by_user' => $request->user() ? $this->isLikedBy($request->user()) : false,
            'tags'=>$this->tags()->get(),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
