<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Traits\ApiResponseTrait;

class UserController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display user by username.
     */
    public function showByUsername($username)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        return $this->successResponse($user, 'User retrieved successfully');
    }

    /**
     * Get current user profile.
     */
    public function profile(Request $request)
    {
        return $this->successResponse($request->user(), 'Profile retrieved successfully');
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:users,username,' . $request->user()->id,
            'email' => 'sometimes|email|unique:users,email,' . $request->user()->id,
        ]);

        $request->user()->update($validated);

        return $this->successResponse($request->user(), 'Profile updated successfully');
    }
}
