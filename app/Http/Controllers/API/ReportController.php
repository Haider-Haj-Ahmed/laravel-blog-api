<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(['post', 'blog', 'comment', 'user'])],
            'id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:64'],
            'details' => ['nullable', 'string', 'max:5000'],
        ]);

        $reporter = $request->user();
        $reportable = $this->resolveReportable($validated['kind'], (int) $validated['id']);

        if (! $reportable) {
            return $this->notFoundResponse('Report target not found');
        }

        if ($reportable instanceof User && $reportable->id === $reporter->id) {
            return $this->validationErrorResponse([
                'id' => ['You cannot report your own account this way.'],
            ]);
        }

        if (! ($reportable instanceof User) && (int) $reportable->user_id === $reporter->id) {
            return $this->validationErrorResponse([
                'id' => ['You cannot report your own content.'],
            ]);
        }

        $report = Report::firstOrCreate([
            'reporter_id' => $reporter->id,
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id' => $reportable->getKey(),
        ], [
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
            'status' => Report::STATUS_PENDING,
        ]);

        if (! $report->wasRecentlyCreated) {
            return $this->successResponse([
                'id' => $report->id,
                'status' => $report->status,
            ], 'Report already submitted');
        }

        return $this->createdResponse([
            'id' => $report->id,
            'status' => $report->status,
        ], 'Report submitted successfully');
    }

    private function resolveReportable(string $kind, int $id): ?Model
    {
        return match ($kind) {
            'post' => Post::query()->find($id),
            'blog' => Blog::query()->find($id),
            'comment' => Comment::query()->find($id),
            'user' => User::query()->find($id),
            default => null,
        };
    }
}
