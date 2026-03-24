<?php

namespace App\Http\Resources;

use App\Models\Blog;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row in the saved list (Instagram-style; extend with more kinds later).
 *
 * @mixin \App\Models\Save
 */
class SavedItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $save = $this->resource;
        $saveable = $save->saveable;

        if (!$saveable) {
            return [
                'kind' => 'unknown',
                'saved_at' => $save->created_at->toDateTimeString(),
                'data' => null,
            ];
        }

        $kind = $saveable instanceof Post
            ? 'post'
            : ($saveable instanceof Blog ? 'blog' : 'unknown');

        return [
            'kind' => $kind,
            'saved_at' => $save->created_at->toDateTimeString(),
            'data' => $saveable instanceof Post
                ? new PostResource($saveable)
                : ($saveable instanceof Blog ? new BlogResource($saveable) : null),
        ];
    }
}
