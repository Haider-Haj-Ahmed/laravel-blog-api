<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSummaryResource;
use App\Notifications\UserFollowedNotification;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\RecommendationCacheService;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly RecommendationCacheService $recommendationCacheService)
    {
    }

    /**
     * Display user by username.
     */
    public function showByUsername($username)
    {
        $user = User::where('username', $username)
            ->with('profile')
            ->first();

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        return $this->successResponse(new UserSummaryResource($user), 'User retrieved successfully');
    }

    /**
     * Get current user profile.
     */
    public function profile(Request $request)
    {
        return $this->successResponse($request->user(), 'Profile retrieved successfully');
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:users,username,' . $request->user()->id,
            'email' => 'sometimes|email|unique:users,email,' . $request->user()->id,
        ]);

        $request->user()->update($validated);

        return $this->successResponse($request->user(), 'Profile updated successfully');
    }

    public function follow(Request $request, string $username)
    {
        $actor = $request->user();
        $targetUser = User::where('username', $username)->first();

        if (! $targetUser) {
            return $this->notFoundResponse('User not found');
        }

        if ($actor->id === $targetUser->id) {
            return $this->validationErrorResponse([
                'user' => ['You cannot follow yourself.'],
            ]);
        }

        $created = DB::transaction(function () use ($actor, $targetUser) {
            $inserted = DB::table('follows')->insertOrIgnore([
                'follower_id' => $actor->id,
                'followed_id' => $targetUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted !== 1) {
                return false;
            }

            User::whereKey($actor->id)->increment('following_count');
            User::whereKey($targetUser->id)->increment('followers_count');

            return true;
        });

        $targetUser->refresh();

        if (! $created) {
            return $this->successResponse([
                'is_following' => true,
                'followers_count' => (int) $targetUser->followers_count,
            ], 'Already following user');
        }

        $targetUser->notify(new UserFollowedNotification($actor));

        $this->recommendationCacheService->bumpUserVersion($actor->id);

        Cache::forget("user:{$actor->id}:following_ids");
        Cache::forget("user:{$targetUser->id}:follower_ids");
        return $this->successResponse([
            'is_following' => true,
            'followers_count' => (int) $targetUser->followers_count,
        ], 'User followed successfully');
    }

    public function unfollow(Request $request, string $username)
    {
        $actor = $request->user();
        $targetUser = User::where('username', $username)->first();

        if (! $targetUser) {
            return $this->notFoundResponse('User not found');
        }

        $deleted = DB::transaction(function () use ($actor, $targetUser) {
            $deleted = DB::table('follows')
                ->where('follower_id', $actor->id)
                ->where('followed_id', $targetUser->id)
                ->delete();

            if ($deleted !== 1) {
                return false;
            }

            User::whereKey($actor->id)->where('following_count', '>', 0)->decrement('following_count');
            User::whereKey($targetUser->id)->where('followers_count', '>', 0)->decrement('followers_count');

            return true;
        });

        if (! $deleted) {
            return $this->notFoundResponse('You are not following this user');
        }

        $targetUser->refresh();
        $this->recommendationCacheService->bumpUserVersion($actor->id);

        Cache::forget("user:{$actor->id}:following_ids");
        Cache::forget("user:{$targetUser->id}:follower_ids");
        return $this->successResponse([
            'is_following' => false,
            'followers_count' => (int) $targetUser->followers_count,
        ], 'User unfollowed successfully');
    }
}
