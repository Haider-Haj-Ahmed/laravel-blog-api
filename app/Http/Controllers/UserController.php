<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    use ApiResponseTrait;
    public function showByUsername($username)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        $posts = $user->posts()->latest()->paginate(15);

        return $this->successResponse([
            'user' => $user,
            'posts' => $posts,
        ], 'User details retrieved successfully');
    }

}
