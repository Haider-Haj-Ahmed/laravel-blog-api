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
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();
        $firstPhoto = $photos->first();
        $legacyPhotoUrl = $this->photo ? asset("storage/post_photos/{$this->photo}") : null;
        $hasPhoto = $firstPhoto !== null || !empty($this->photo);

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
            'code_language' => $this->code_language,
            'photo_url' => $firstPhoto ? asset("storage/{$firstPhoto->path}") : $legacyPhotoUrl,
            'photos' => $photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => asset("storage/{$photo->path}"),
                'sort_order' => $photo->sort_order,
            ])->values(),
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
            //saves count
            'views_count' => isset($this->views_count) ? (int) $this->views_count : $this->views()->count(),
            'is_liked_by_user' => $request->user() ? $this->isLikedBy($request->user()) : false,
            'tags'=>TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
