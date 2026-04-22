<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Post;
use App\Models\Profile;
use App\Models\View;
use App\Services\RecommendationCacheService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ViewController extends Controller
{
	use ApiResponseTrait;

	public function __construct(private readonly RecommendationCacheService $recommendationCacheService)
	{
	}

	public function store(Request $request)
	{
		$validated = $request->validate([
			'type' => 'required|in:post,blog,profile',
			'id' => 'required|integer|min:1',
		]);

		$viewable = $this->resolveViewable($validated['type'], (int) $validated['id']);

		if (! $viewable) {
			return $this->notFoundResponse('Content not found');
		}

		if (
			in_array($validated['type'], ['post', 'blog'], true)
			&& ! $viewable->is_published
			&& $request->user()->id !== $viewable->user_id
		) {
			return $this->forbiddenResponse('You are not authorized to view this content');
		}

		$view = View::query()->firstOrCreate([
			'user_id' => $request->user()->id,
			'viewable_type' => $viewable->getMorphClass(),
			'viewable_id' => $viewable->getKey(),
		]);

		if ($view->wasRecentlyCreated) {
			$viewable->increment('views_count');
			$viewable->refresh();
		}

		if ($validated['type'] === 'post' && $view->wasRecentlyCreated) {
			$this->recommendationCacheService->bumpUserVersion($request->user()->id);
		}

		return $this->successResponse([
			'view_recorded' => true,
			'already_viewed' => ! $view->wasRecentlyCreated,
			'type' => $validated['type'],
			'id' => $viewable->getKey(),
			'views_count' => (int) ($viewable->views_count ?? 0),
		], $view->wasRecentlyCreated ? 'View recorded successfully' : 'Already viewed');
	}

	private function resolveViewable(string $type, int $id): Post|Blog|Profile|null
	{
		return match ($type) {
			'post' => Post::query()->find($id),
			'blog' => Blog::query()->find($id),
			'profile' => Profile::query()->find($id),
			default => null,
		};
	}
}
