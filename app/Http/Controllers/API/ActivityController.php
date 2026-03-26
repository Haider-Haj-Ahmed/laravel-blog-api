<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $activities = Activity::query()
            ->where('owner_user_id', $request->user()->id)
            ->with('actor')
            ->latest()
            ->paginate(20);

        return $this->paginatedResponse(
            ActivityResource::collection($activities),
            'Activity history retrieved successfully'
        );
    }
}
