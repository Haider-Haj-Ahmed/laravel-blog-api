<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BlogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'cover_image_url' => $this->cover_image_path ? Storage::url($this->cover_image_path) : null,
            'reading_time'=>$this->reading_time,
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
            'tags'=>TagResource::collection($this->whenLoaded('tags')),
            'sections'=>SectionResource::collection($this->whenLoaded('sections')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
