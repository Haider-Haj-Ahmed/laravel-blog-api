<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Notification;
use App\Notifications\OtpNotification;
use App\Traits\ApiResponseTrait;

class OtpController extends Controller
{
    use ApiResponseTrait;

    protected $ttlMinutes = 10;
    protected $maxAttempts = 5;

    // POST /api/otp/verify
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
        ]);
        $user = User::where('email', $request->email)
            ->orWhere('pending_email', $request->email)
            ->first();

        if (! $user) {
            return $this->notFoundResponse('User not found');
        }

        $otp = Otp::where('user_id', $user->id)->latest()->first();

        if (!$otp) {
            return $this->notFoundResponse('OTP not found');
        }

        if ($otp->isExpired()) {
            return $this->errorResponse('OTP expired', 422);
        }
        // if($request->user()->id != $user->id) {
        //     return $this->unauthorizedResponse('you are not autohrized to verfiy this account');
        // }

        if ($otp->attempts >= $this->maxAttempts) {
            return $this->errorResponse('Max verification attempts exceeded', 429);
        }

        if (!Hash::check($request->code, $otp->code)) {
            $otp->increment('attempts');
            return $this->validationErrorResponse(['code' => ['Invalid code']], 'Invalid OTP');
        }


        // success: mark the relevant verified timestamp on the user (email or phone)
        $user = $otp->user;
        if ($otp->channel === 'sms') {
            $user->phone_verified_at = $user->phone_verified_at ?? now();
        } else {
            // If this verifies a pending email change, swap it in now.
            if (!empty($user->pending_email) && strcasecmp($user->pending_email, $request->email) === 0) {
                $user->email = $user->pending_email;
                $user->pending_email = null;
            }

            $user->email_verified_at = $user->email_verified_at ?? now();
        }
        $user->save();

        Otp::where('user_id', $user->id)->delete();

        // Generate authentication token after successful OTP verification
        $token = $this->createAccessToken($user);

        return $this->successResponse([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'OTP verified successfully. You are now authenticated.');
    }

    // POST /api/otp/resend
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'channel' => 'nullable|in:email,sms'
        ]);

        $user = User::where('email', $request->email)
            ->orWhere('pending_email', $request->email)
            ->first();

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        $isPendingEmailVerification = !empty($user->pending_email)
            && strcasecmp($user->pending_email, $request->email) === 0;

        if (! $isPendingEmailVerification && ($user->email_verified_at || $user->phone_verified_at)) {
            return $this->validationErrorResponse([
                'user_id' => ['This account is already verified.'],
            ]);
        }

        $channel = $isPendingEmailVerification ? 'email' : ($request->channel ?? 'email');
        $throttleKey = sprintf('otp-resend:%s:%s:%s', $user->id, $channel, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return $this->errorResponse(
                'Too many OTP resend attempts. Please try again later.',
                429,
                ['retry_after' => RateLimiter::availableIn($throttleKey)]
            );
        }

        $plain = $this->generateCode();
        $hashed = Hash::make($plain);
        $expiresAt = Carbon::now()->addMinutes($this->ttlMinutes);

        Otp::where('user_id', $user->id)->delete();

        $otp = Otp::create([
            'user_id' => $user->id,
            'code' => $hashed,
            'channel' => $channel,
            'expires_at' => $expiresAt,
        ]);

        RateLimiter::hit($throttleKey, $this->ttlMinutes * 60);

        // notify
        $notification = new OtpNotification($plain, $otp->channel);
        if ($isPendingEmailVerification) {
            Notification::route('mail', $user->pending_email)->notify($notification);
        } else {
            $user->notify($notification);
        }

        // if sms channel and Twilio configured, send SMS explicitly
        if ($otp->channel === 'sms') {
            $notification->toSms($user);
        }

        return $this->successResponse(null, 'OTP resent');
    }

    protected function generateCode(): string
    {
        return (string) random_int(100000, 999999); // 6-digit
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
