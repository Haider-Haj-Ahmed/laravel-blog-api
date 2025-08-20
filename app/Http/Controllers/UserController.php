<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function showByUsername($username)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }

}
