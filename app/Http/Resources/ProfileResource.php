<?php

namespace App\Http\Resources;

use App\Models\User;
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
        /** @var User|null $viewer */
        $viewer = auth('sanctum')->user();
        $tags = $profile?->relationLoaded('tags') ? $profile->tags : ($profile ? $profile->tags()->get() : collect());

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
            'badge' => $profile?->badge,
            'posts_count' => (int) ($this->published_posts_count ?? 0),
            'blogs_count' => (int) ($this->published_blogs_count ?? 0),
            'views_count' => (int) ($profile?->views_count ?? 0),
            'is_viewed' => $this->resolveIsViewed($request),
            'followers_count' => (int) ($this->followers_count ?? 0),
            'following_count' => (int) ($this->following_count ?? 0),
            'is_following' => $viewer ? $viewer->isFollowing($this->resource) : false,
            'joined_at' => $this->created_at,
            'last_seen_at' => $profile?->last_seen_at,
            'tags' => $tags,
        ];
    }

    private function resolveIsViewed(Request $request): bool
    {
        $profile = $this->profile;
        $viewer = auth('sanctum')->user();

        if (! $viewer || ! $profile) {
            return false;
        }

        if (array_key_exists('is_viewed', $profile->getAttributes())) {
            return (bool) $profile->is_viewed;
        }

        if ($profile->relationLoaded('views')) {
            return $profile->views->contains('user_id', $viewer->id);
        }

        return $profile->views()->where('user_id', $viewer->id)->exists();
    }
}
