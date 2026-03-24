<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\SavedItemResource;
use App\Models\Blog;
use App\Models\Post;
use App\Models\Save;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedController extends Controller
{
    use ApiResponseTrait;

    /**
     * List saved items (posts, blogs, or both). Instagram-style: one "Saved" feed; filter by `type`.
     *
     * Query: type = post | blog | all (default: all)
     */
    public function index(Request $request)
    {
        $type = $request->query('type', 'all');

        if (! in_array($type, ['post', 'blog', 'all'], true)) {
            return $this->validationErrorResponse(
                ['type' => ['The type must be one of: post, blog, all.']],
                'Validation failed'
            );
        }

        $query = Save::query()
            ->where('user_id', $request->user()->id)
            ->whereHasMorph('saveable', [Post::class, Blog::class], function ($q, $morphClass) {
                $q->where('is_published', true);
            })
            ->with(['saveable' => function ($morphTo) {
                $morphTo->morphWith([
                    Post::class => ['user'],
                    Blog::class => ['user'],
                ]);
            }]);

        if ($type === 'post') {
            $query->where('saveable_type', (new Post)->getMorphClass());
        } elseif ($type === 'blog') {
            $query->where('saveable_type', (new Blog)->getMorphClass());
        }

        $saves = $query->latest('created_at')->paginate(15);

        $saves->getCollection()->transform(function (Save $save) {
            if ($save->saveable instanceof Post) {
                $save->saveable->loadCount(['comments', 'likes']);
            }

            return $save;
        });

        return $this->paginatedResponse(
            SavedItemResource::collection($saves),
            'Saved items retrieved successfully'
        );
    }

    /**
     * Save a published post or blog (bookmark).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['post', 'blog'])],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $model = $this->resolveSaveable($validated['type'], $validated['id']);

        if (! $model) {
            return $this->notFoundResponse('Post or blog not found');
        }

        if (! $model->is_published) {
            return $this->notFoundResponse('Content not available');
        }

        $save = Save::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'saveable_type' => $model->getMorphClass(),
                'saveable_id' => $model->getKey(),
            ],
        );

        return $this->successResponse(
            [
                'saved' => true,
                'kind' => $validated['type'],
                'saved_at' => $save->created_at->toDateTimeString(),
            ],
            $save->wasRecentlyCreated ? 'Saved successfully' : 'Already saved'
        );
    }

    /**
     * Remove from saved (unbookmark).
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['post', 'blog'])],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $model = $this->resolveSaveable($validated['type'], $validated['id']);

        if (! $model) {
            return $this->notFoundResponse('Post or blog not found');
        }

        $deleted = Save::query()
            ->where('user_id', $request->user()->id)
            ->where('saveable_type', $model->getMorphClass())
            ->where('saveable_id', $model->getKey())
            ->delete();

        if (! $deleted) {
            return $this->notFoundResponse('Not in saved list');
        }

        return $this->successResponse(null, 'Removed from saved');
    }

    private function resolveSaveable(string $type, int $id): Post|Blog|null
    {
        return match ($type) {
            'post' => Post::query()->find($id),
            'blog' => Blog::query()->find($id),
            default => null,
        };
    }
}
