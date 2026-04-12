<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserSummaryResource extends JsonResource
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
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'avatar_url' => $profile?->avatar ? asset("storage/avatars/{$profile->avatar}") : asset('images/default-avatar.png'),
            'bio' => $profile?->bio,
            'badge' => $profile?->badge ?? 'junior',
        ];
    }
}
