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
            'is_modified' => (bool) ($this->is_modified ?? false),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'name' => $this->user->name,
                    'avatar_url' => $this->user->profile?->avatar ? asset("storage/avatars/{$this->user->profile->avatar}") : asset('images/default-avatar.png'),
                    'badge' => $this->user->profile?->badge,
                ];
            }),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'likes_count' => (int) ($this->likes_count ?? 0),
            'is_liked_by_user' => $request->user() ? $this->isLikedBy($request->user()) : false,
            'tags'=>TagResource::collection($this->whenLoaded('tags')),
            'views_count' => (int) ($this->views_count ?? 0),
            'is_viewed' => $this->resolveIsViewed($request),
            'sections'=>SectionResource::collection($this->whenLoaded('sections')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveIsViewed(Request $request): bool
    {
        $viewer = $request->user();

        if (! $viewer) {
            return false;
        }

        if (array_key_exists('is_viewed', $this->resource->getAttributes())) {
            return (bool) $this->is_viewed;
        }

        if ($this->relationLoaded('views')) {
            return $this->views->contains('user_id', $viewer->id);
        }

        return $this->views()->where('user_id', $viewer->id)->exists();
    }
}
