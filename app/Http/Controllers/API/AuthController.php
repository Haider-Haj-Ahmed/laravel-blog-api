<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Traits\ApiResponseTrait;
use App\Models\Otp;
use Carbon\Carbon;
use App\Notifications\OtpNotification;
use App\Http\Controllers\API\OtpController;

class AuthController extends Controller
{
    use ApiResponseTrait;
    public function register(RegisterRequest $request)
    {
        $credentials = $request->validated();

        $user = User::create([
            'name' => $credentials['name'],
            'email' => $credentials['email'],
            'username' => $credentials['username'],
            'password' => bcrypt($credentials['password']),
        ]);

        // create OTP
        $plain = random_int(100000, 999999);
        $hashed = Hash::make((string) $plain);
        $expiresAt = Carbon::now()->addMinutes(10);

        Otp::create([
            'user_id' => $user->id,
            'code' => $hashed,
            'channel' => $request->get('channel', 'email'),
            'expires_at' => $expiresAt,
        ]);

        // send notification
        $user->notify(new OtpNotification((string) $plain, $request->get('channel', 'email')));

        // If an OTP code was provided during registration, attempt to verify it immediately
        if ($request->filled('code')) {
            // Build a request to pass to the OtpController verify method
            $verifyRequest = new Request([
                'user_id' => $user->id,
                'code' => $request->get('code'),
                'create_token' => true,
            ]);

            $otpController = app(OtpController::class);
            $verifyResponse = $otpController->verify($verifyRequest);

            // If OTP verification succeeded, return whatever the verification response returned
            if ($verifyResponse->getStatusCode() === 200) {
                return $verifyResponse;
            }

            // If verification failed, return the verification response
            return $verifyResponse;
        }

        // Otherwise, don't create an auth token until the user verifies via the OTP endpoint
        return $this->createdResponse([
            'user' => $user,
            'user_id' => $user->id,
        ], 'User registered successfully. OTP was sent; verify with /api/otp/verify to get an auth token.');
    }


    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return $this->unauthorizedResponse('Invalid credentials');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return $this->successResponse(null, 'Logged out successfully');
    }
}
