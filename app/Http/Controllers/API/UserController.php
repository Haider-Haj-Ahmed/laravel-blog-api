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
        $targetUser = User::where('username', $username)->first();

        if (! $targetUser) {
            return $this->notFoundResponse('User not found');
        }

        if ($request->user()->id === $targetUser->id) {
            return $this->validationErrorResponse([
                'user' => ['You cannot follow yourself.'],
            ]);
        }

        if ($request->user()->isFollowing($targetUser)) {
            return $this->successResponse([
                'is_following' => true,
                'followers_count' => $targetUser->followers()->count(),
            ], 'Already following user');
        }

        $request->user()->following()->attach($targetUser->id);
        $targetUser->notify(new UserFollowedNotification($request->user()));

        $this->recommendationCacheService->bumpUserVersion($request->user()->id);

        Cache::forget("user:{$request->user()->id}:following_ids");
        Cache::forget("user:{$targetUser->id}:follower_ids");
        return $this->successResponse([
            'is_following' => true,
            'followers_count' => $targetUser->followers()->count(),
        ], 'User followed successfully');
    }

    public function unfollow(Request $request, string $username)
    {
        $targetUser = User::where('username', $username)->first();

        if (! $targetUser) {
            return $this->notFoundResponse('User not found');
        }

        $deleted = $request->user()->following()->detach($targetUser->id);

        if (! $deleted) {
            return $this->notFoundResponse('You are not following this user');
        }

        $this->recommendationCacheService->bumpUserVersion($request->user()->id);

        Cache::forget("user:{$request->user()->id}:following_ids");
        Cache::forget("user:{$targetUser->id}:follower_ids");
        return $this->successResponse([
            'is_following' => false,
            'followers_count' => $targetUser->followers()->count(),
        ], 'User unfollowed successfully');
    }
}
