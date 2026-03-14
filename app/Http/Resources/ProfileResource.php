<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $profile?->avatar ? asset("storage/avatars/{$profile->avatar}") : asset('images/default-avatar.png'),
            'bio' => $profile?->bio,
            'website' => $profile?->website,
            'location' => $profile?->location,
            'social_links' => $profile?->social_links ?? [],
            'cover_image_url' => $profile?->cover_image ? asset("storage/covers/{$profile->cover_image}") : null,
            'ranking_points' => $profile?->ranking_points ?? 0,
            'badge' => $profile?->badge ?? 'junior',
            'posts_count' => $this->posts()->count(),
            'blogs_count' => $this->blogs()->count(),
            'joined_at' => $this->created_at,
            'last_seen_at' => $profile?->last_seen_at,
        ];
    }
}
