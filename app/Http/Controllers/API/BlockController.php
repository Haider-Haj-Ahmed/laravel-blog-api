<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use App\Services\RecommendationCacheService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BlockController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly RecommendationCacheService $recommendationCacheService) {}

    public function index(Request $request)
    {
        $blocked = $request->user()
            ->blockedUsers()
            ->orderBy('user_blocks.created_at', 'desc')
            ->paginate(20);

        return $this->paginatedResponse(
            UserSummaryResource::collection($blocked),
            'Blocked users retrieved successfully'
        );
    }

    public function store(Request $request, string $username)
    {
        $actor = $request->user();
        $target = User::findByUsername($username);

        if (! $target) {
            return $this->notFoundResponse('User not found');
        }

        if ($target->id === $actor->id) {
            return $this->validationErrorResponse([
                'username' => ['You cannot block yourself.'],
            ]);
        }

        DB::transaction(function () use ($actor, $target): void {
            DB::table('user_blocks')->insertOrIgnore([
                'user_id' => $actor->id,
                'blocked_user_id' => $target->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $actorToTargetDeleted = DB::table('follows')
                ->where('follower_id', $actor->id)
                ->where('followed_id', $target->id)
                ->delete();

            if ($actorToTargetDeleted === 1) {
                User::whereKey($actor->id)->update([
                    'following_count' => DB::raw('CASE WHEN following_count > 0 THEN following_count - 1 ELSE 0 END'),
                ]);
                User::whereKey($target->id)->update([
                    'followers_count' => DB::raw('CASE WHEN followers_count > 0 THEN followers_count - 1 ELSE 0 END'),
                ]);
            }

            $targetToActorDeleted = DB::table('follows')
                ->where('follower_id', $target->id)
                ->where('followed_id', $actor->id)
                ->delete();

            if ($targetToActorDeleted === 1) {
                User::whereKey($target->id)->update([
                    'following_count' => DB::raw('CASE WHEN following_count > 0 THEN following_count - 1 ELSE 0 END'),
                ]);
                User::whereKey($actor->id)->update([
                    'followers_count' => DB::raw('CASE WHEN followers_count > 0 THEN followers_count - 1 ELSE 0 END'),
                ]);
            }
        });

        $this->recommendationCacheService->bumpUserVersion($actor->id);
        $this->recommendationCacheService->bumpUserVersion($target->id);

        Cache::forget("user:{$actor->id}:following_ids");
        Cache::forget("user:{$actor->id}:follower_ids");
        Cache::forget("user:{$target->id}:following_ids");
        Cache::forget("user:{$target->id}:follower_ids");

        return $this->successResponse(null, 'User blocked successfully');
    }

    public function destroy(Request $request, string $username)
    {
        $actor = $request->user();
        $target = User::findByUsername($username);

        if (! $target) {
            return $this->notFoundResponse('User not found');
        }

        $actor->blockedUsers()->detach($target->id);
        $this->recommendationCacheService->bumpUserVersion($actor->id);

        return $this->successResponse(null, 'User unblocked successfully');
    }
}
