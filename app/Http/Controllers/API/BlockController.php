<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use App\Services\RecommendationCacheService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

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

        $actor->blockedUsers()->syncWithoutDetaching([$target->id]);
        $this->recommendationCacheService->bumpUserVersion($actor->id);

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
