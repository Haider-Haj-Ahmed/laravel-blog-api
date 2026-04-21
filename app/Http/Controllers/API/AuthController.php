<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LogoutRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Hash;
use App\Traits\ApiResponseTrait;
use App\Models\Otp;
use Carbon\Carbon;
use App\Notifications\OtpNotification;
use App\Http\Controllers\API\OtpController;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    use ApiResponseTrait;
    public function register(RegisterRequest $request)
    {
        $credentials = $request->validated();

        try {
            $user = DB::transaction(function () use ($credentials) {
                $user = User::create([
                    'name' => $credentials['name'],
                    'email' => $credentials['email'],
                    'username' => $credentials['username'],
                    'password' => bcrypt($credentials['password']),
                ]);

                Profile::create([
                    'user_id' => $user->id,
                    'ranking_points' => 0,
                    'bio' => $credentials['bio'] ?? null,
                    'avatar' => $credentials['avatar'] ?? null,
                    'website' => $credentials['website'] ?? null,
                    'location' => $credentials['location'] ?? null,
                ]);

                return $user;
            });
        } catch (Throwable $exception) {
            Log::error('Registration transaction failed.', [
                'email' => $credentials['email'] ?? null,
                'username' => $credentials['username'] ?? null,
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Registration failed. Please try again.', 500);
        }

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

        if (! $user->email_verified_at && ! $user->phone_verified_at) {
            return $this->forbiddenResponse('Account verification is required before login.');
        }

        // Ensure user has a profile (for legacy users)
        if (!$user->profile) {
            Profile::create([
                'user_id' => $user->id,
                'ranking_points' => 0,
            ]);
        }

        $token = $this->createAccessToken($user);

        return $this->successResponse([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    public function logout(LogoutRequest $request)
    {
        $user = $request->user();
        $logoutAllDevices = $request->boolean('all_devices') || $request->input('scope') === 'all';

        if ($logoutAllDevices) {
            $user->tokens()->delete();

            return $this->successResponse(null, 'Logged out from all devices successfully');
        }

        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();

            return $this->successResponse(null, 'Logged out from current device successfully');
        }

        return $this->successResponse(null, 'No active access token found for current device.');
    }

    private function createAccessToken(User $user): string
    {
        $expirationMinutes = config('sanctum.expiration');

        if (is_numeric($expirationMinutes) && (int) $expirationMinutes > 0) {
            return $user->createToken('auth_token', ['*'], now()->addMinutes((int) $expirationMinutes))->plainTextToken;
        }

        return $user->createToken('auth_token')->plainTextToken;
    }
}
