<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Profile;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use ApiResponseTrait;

    public function show(Request $request)
    {
        $user = $request->user();

        if (! $user->profile) {
            Profile::create([
                'user_id' => $user->id,
                'ranking_points' => 0,
            ]);
            $user->refresh();
        }

        $profile = $user->profile;
        $this->authorize('view', $profile);

        return $this->successResponse([
            'settings' => $profile->settings ?? [],
        ], 'Settings retrieved successfully');
    }

    public function update(UpdateSettingsRequest $request)
    {
        $user = $request->user();

        if (! $user->profile) {
            Profile::create([
                'user_id' => $user->id,
                'ranking_points' => 0,
            ]);
            $user->refresh();
        }

        $profile = $user->profile;
        $this->authorize('update', $profile);

        $current = $profile->settings ?? [];
        $incoming = $request->validated();
        $profile->settings = array_replace($current, $incoming);
        $profile->save();

        return $this->successResponse([
            'settings' => $profile->settings,
        ], 'Settings updated successfully');
    }
}
