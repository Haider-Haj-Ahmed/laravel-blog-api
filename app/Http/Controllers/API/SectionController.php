<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderBlogSectionsRequest;
use App\Http\Requests\StoreBlogSectionRequest;
use App\Http\Requests\UpdateBlogSectionRequest;
use App\Http\Resources\BlogResource;
use App\Http\Resources\SectionResource;
use App\Models\Blog;
use App\Models\Section;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SectionController extends Controller
{
    use ApiResponseTrait;

    public function store(StoreBlogSectionRequest $request, Blog $blog)
    {
        $this->authorize('update', $blog);

        $validated = $request->validated();

        if ($blog->sections()->where('order', $validated['order'])->exists()) {
            return $this->validationErrorResponse([
                'order' => ['The selected order is already used in this blog.'],
            ]);
        }

        $section = $blog->sections()->create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'order' => $validated['order'],
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('section_images', 'public');
            $section->update(['image_path' => $path]);
        }

        return $this->createdResponse(
            new SectionResource($section->fresh()),
            'Blog section created successfully'
        );
    }

    public function update(UpdateBlogSectionRequest $request, Blog $blog, Section $section)
    {
        $this->authorize('update', $blog);

        if ($section->blog_id !== $blog->id) {
            return $this->notFoundResponse('Section not found for this blog');
        }

        $validated = $request->validated();
        $newImagePath = null;
        $oldImagePathToDelete = null;

        try {
            DB::transaction(function () use ($request, $section, $validated, &$newImagePath, &$oldImagePathToDelete): void {
                $attributes = $validated;

                if ($request->hasFile('image')) {
                    $newImagePath = $request->file('image')->store('section_images', 'public');
                    if ($section->image_path) {
                        $oldImagePathToDelete = $section->image_path;
                    }
                    $attributes['image_path'] = $newImagePath;
                }

                if (($validated['remove_image'] ?? false) === true) {
                    if (! $oldImagePathToDelete && $section->image_path) {
                        $oldImagePathToDelete = $section->image_path;
                    }
                    $attributes['image_path'] = null;
                }

                unset($attributes['image']);
                unset($attributes['remove_image']);

                if (isset($attributes['order'])) {
                    $isOrderUsed = Section::query()
                        ->where('blog_id', $section->blog_id)
                        ->where('order', $attributes['order'])
                        ->where('id', '!=', $section->id)
                        ->exists();

                    if ($isOrderUsed) {
                        throw new \InvalidArgumentException('Order is already used in this blog.');
                    }
                }

                if (! empty($attributes)) {
                    $section->update($attributes);
                }
            });
        } catch (\InvalidArgumentException $e) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            return $this->validationErrorResponse([
                'order' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            return $this->errorResponse('Failed to update section', 500);
        }

        if ($oldImagePathToDelete) {
            Storage::disk('public')->delete($oldImagePathToDelete);
        }

        return $this->successResponse(
            new SectionResource($section->fresh()),
            'Blog section updated successfully'
        );
    }

    public function destroy(Blog $blog, Section $section)
    {
        $this->authorize('update', $blog);

        if ($section->blog_id !== $blog->id) {
            return $this->notFoundResponse('Section not found for this blog');
        }

        $imagePathToDelete = $section->image_path;

        DB::transaction(function () use ($blog, $section): void {
            $sections = $blog->sections()->orderBy('order')->get(['id', 'order']);
            $temporaryOffset = ((int) $sections->max('order')) + $sections->count() + 1;

            $section->delete();

            $remainingSectionIds = $blog->sections()
                ->orderBy('order')
                ->pluck('id')
                ->all();

            foreach ($remainingSectionIds as $index => $sectionId) {
                Section::query()
                    ->whereKey($sectionId)
                    ->update(['order' => $temporaryOffset + $index]);
            }

            foreach ($remainingSectionIds as $index => $sectionId) {
                Section::query()
                    ->whereKey($sectionId)
                    ->update(['order' => $index]);
            }
        });

        if ($imagePathToDelete) {
            Storage::disk('public')->delete($imagePathToDelete);
        }

        return $this->successResponse(null, 'Blog section deleted successfully');
    }

    public function reorder(ReorderBlogSectionsRequest $request, Blog $blog)
    {
        $this->authorize('update', $blog);

        $sections = $request->validated()['sections'];
        $blogSectionIds = $blog->sections()->pluck('id')->all();
        $incomingSectionIds = array_map(static fn (array $item): int => (int) $item['id'], $sections);

        $invalidIds = array_diff($incomingSectionIds, $blogSectionIds);
        if (! empty($invalidIds)) {
            return $this->validationErrorResponse([
                'sections' => ['One or more sections do not belong to this blog.'],
            ]);
        }

        DB::transaction(function () use ($sections): void {
            $temporaryOffset = ((int) collect($sections)->max('order')) + count($sections) + 1;

            foreach ($sections as $index => $payload) {
                Section::query()
                    ->whereKey($payload['id'])
                    ->update(['order' => $temporaryOffset + $index]);
            }

            foreach ($sections as $payload) {
                Section::query()
                    ->whereKey($payload['id'])
                    ->update(['order' => $payload['order']]);
            }
        });

        $blog->load(['user', 'tags', 'sections']);

        return $this->successResponse(
            new BlogResource($blog),
            'Blog sections reordered successfully'
        );
    }
}
