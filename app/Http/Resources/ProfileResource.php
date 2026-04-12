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
        $viewer = auth('sanctum')->user();
        $tags = $profile?->relationLoaded('tags') ? $profile->tags : ($profile ? $profile->tags()->get() : collect());
        $postsCount = $this->published_posts_count ?? $this->posts()->where('is_published', true)->count();
        $blogsCount = $this->published_blogs_count ?? $this->blogs()->where('is_published', true)->count();
        $followersCount = $this->followers_count ?? $this->followers()->count();
        $followingCount = $this->following_count ?? $this->following()->count();
        $viewsCount = $profile ? $profile->views()->count() : 0;

        return [
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->when($viewer && $viewer->id === $this->id, $this->email),
            'avatar_url' => $profile?->avatar ? asset("storage/avatars/{$profile->avatar}") : asset('images/default-avatar.png'),
            'bio' => $profile?->bio,
            'website' => $profile?->website,
            'location' => $profile?->location,
            'social_links' => $profile?->social_links ?? [],
            'cover_image_url' => $profile?->cover_image ? asset("storage/covers/{$profile->cover_image}") : null,
            'ranking_points' => $profile?->ranking_points ?? 0,
            'badge' => $profile?->badge ?? 'junior',
            'posts_count' => $postsCount,
            'blogs_count' => $blogsCount,
            'views_count' => $viewsCount,
            'followers_count' => $followersCount,
            'following_count' => $followingCount,
            'is_following' => $viewer ? $viewer->isFollowing($this->resource) : false,
            'joined_at' => $this->created_at,
            'last_seen_at' => $profile?->last_seen_at,
            'tags' => $tags,
        ];
    }
}
