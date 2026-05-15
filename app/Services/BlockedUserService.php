<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserBlock;

class BlockedUserService
{
    /**
     * @return list<int>
     */
    public function blockedUserIds(User $viewer): array
    {
        return UserBlock::query()
            ->where('user_id', $viewer->id)
            ->pluck('blocked_user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function isBlockedEitherWay(User $viewer, int $otherUserId): bool
    {
        if ($viewer->id === $otherUserId) {
            return false;
        }

        return UserBlock::query()
            ->where(function ($q) use ($viewer, $otherUserId) {
                $q->where('user_id', $viewer->id)
                    ->where('blocked_user_id', $otherUserId);
            })
            ->orWhere(function ($q) use ($viewer, $otherUserId) {
                $q->where('user_id', $otherUserId)
                    ->where('blocked_user_id', $viewer->id);
            })
            ->exists();
    }
}
