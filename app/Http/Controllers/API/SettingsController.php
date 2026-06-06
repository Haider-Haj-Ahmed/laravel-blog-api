<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Profile;
use App\Services\UserSettingsService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use ApiResponseTrait;

    public function show(Request $request, UserSettingsService $settingsService)
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
        $this->authorize('viewSettings', $profile);

        $settings = $settingsService->mergeWithDefaults($profile->settings ?? []);
        if (($profile->settings ?? []) !== $settings) {
            $profile->settings = $settings;
            $profile->save();
        }

        return $this->successResponse([
            'settings' => $settings,
        ], 'Settings retrieved successfully');
    }

    public function update(UpdateSettingsRequest $request, UserSettingsService $settingsService)
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

        $incoming = $request->validated();
        $profile->settings = $settingsService->applyPatch($profile->settings ?? [], $incoming);
        $profile->save();

        return $this->successResponse([
            'settings' => $profile->settings,
        ], 'Settings updated successfully');
    }
}
