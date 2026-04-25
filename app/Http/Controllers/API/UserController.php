<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSummaryResource;
use App\Models\Otp;
use App\Notifications\UserFollowedNotification;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UsernameMap;
use App\Notifications\OtpNotification;
use App\Services\RecommendationCacheService;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly RecommendationCacheService $recommendationCacheService)
    {
    }

    /**
     * Display user by username.
     */
    public function showByUsername($username)
    {
        $user = User::findByUsername($username);

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }
        $user->load('profile');

        return $this->successResponse(new UserSummaryResource($user), 'User retrieved successfully');
    }

    /**
     * Get current user profile.
     */
    public function profile(Request $request)
    {
        return $this->successResponse($request->user(), 'Profile retrieved successfully');
    }

    /**
     * Update username.
     */
    public function updateUsername(Request $request){
        $user=$request->user();
        $atts=$request->validate([
            'username'=> 'required|string|lowercase|max:25|min:1',
            'password'=>'required|string',
        ]);
        if(!Hash::check($atts['password'], $user->password)){
            return $this->validationErrorResponse([
                'password' => ['The provided password is incorrect.'],
            ]);
        }
        $username=$atts['username'];
        $username=trim($username);
        $username=str_replace(' ', '_', $username);
        $reserved = [
            'admin', 'administrator', 'api', 'app', 'root', 'system', 'guest', 'test',
            'users', 'profile', 'profiles', 'auth', 'login', 'register', 'logout',
            'posts', 'blogs', 'comments', 'likes', 'follows', 'followers',
            'dashboard', 'settings', 'help', 'support', 'about', 'contact',
            'search', 'discover', 'trending', 'notifications', 'messages', 'inbox',
            'search', 'api_v1', 'v1', 'v2', 'static', 'assets', 'storage'
        ];
        
        if (in_array(strtolower($username), $reserved)) {
            return $this->validationErrorResponse([
                'username' => ['This username is reserved and cannot be used.'],
            ]);
        }
        
        if(User::where('username', $username)->exists()){
            return $this->validationErrorResponse([
                'username' => ['The username is already taken.'],
            ]);
        }
        try{
            DB::transaction(function () use ($user, $username) {
                $oldUsername = $user->username;
                $user->username = $username;
                $user->save();
                UsernameMap::create([
                    'old'=>$oldUsername,
                    'current'=>$username
                ]);
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update username', 500);
        }
        return $this->successResponse(
            new UserSummaryResource($user),
            'Your username has been successfully updated. Our system will keep track of your previous username to redirect links to the new one.'
        );
    }
    public function updateEmail(Request $request){
        $user=$request->user();
        $atts=$request->validate([
            'email'=> 'required|string|email|max:255',
            'password'=>'required|string',
        ]);
        if(!Hash::check($atts['password'], $user->password)){
            return $this->validationErrorResponse([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (strcasecmp($atts['email'], $user->email) === 0) {
            return $this->validationErrorResponse([
                'email' => ['Please provide a different email address.'],
            ]);
        }

        $emailTaken = User::where(function ($query) use ($atts) {
            $query->where('email', $atts['email'])
                ->orWhere('pending_email', $atts['email']);
        })->where('id', '!=', $user->id)->exists();

        if ($emailTaken) {
            return $this->validationErrorResponse([
                'email' => ['The email is already taken.'],
            ]);
        }

        try{
            DB::transaction(function () use ($user, $atts){
                $user->pending_email = $atts['email'];
                $user->save();

                // create OTP for pending email confirmation
                $plain = random_int(100000, 999999);
                $hashed = Hash::make((string) $plain);
                $expiresAt = Carbon::now()->addMinutes(10);

                Otp::where('user_id', $user->id)->delete();

                Otp::create([
                    'user_id' => $user->id,
                    'code' => $hashed,
                    'channel' => 'email',
                    'expires_at' => $expiresAt,
                ]);

                // send OTP to pending email address (not the current one)
                Notification::route('mail', $atts['email'])
                    ->notify(new OtpNotification((string) $plain, 'email'));

                // force re-auth after requesting email change
                $user->tokens()->delete();
            });
        }catch(\Exception $e){
            return $this->errorResponse(
                'Failed to update email. If you were logged out after this message, please contact the support team.',
                500
            );
        }
            return $this->successResponse(null,'Email change requested. We sent an OTP to your new email. Your current email stays active until verification succeeds.');
    }

    public function forgotPassword(Request $request)
    {
        $atts = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        Password::broker()->sendResetLink([
            'email' => $atts['email'],
        ]);

        // Keep response generic to prevent account enumeration.
        return $this->successResponse(null, 'If your email exists in our system, a password reset link has been sent.');
    }

    public function resetPassword(Request $request)
    {
        $atts = $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $atts['email'],
                'password' => $atts['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $atts['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Invalidate all API sessions after password reset.
                $user->tokens()->delete();
                //this event does not do anything
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->validationErrorResponse([
                'email' => [__($status)],
            ], 'Unable to reset password');
        }

        return $this->successResponse(null, 'Password has been reset successfully.');
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $atts = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed|different:current_password',
        ]);

        if (! Hash::check($atts['current_password'], $user->password)) {
            return $this->validationErrorResponse([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($atts['password']),
            'remember_token' => Str::random(60),
        ])->save();

        // Revoke all API tokens so sessions must be re-authenticated.
        $user->tokens()->delete();

        return $this->successResponse(
            null,
            'Password changed successfully. You have been logged out from all devices. Please log in again.'
        );
    }

    public function changeName(Request $request)
    {
        $user = $request->user();

        $atts = $request->validate([
            'name' => 'required|string|min:2|max:50',
            'password' => 'required|string',
        ]);

        if (! Hash::check($atts['password'], $user->password)) {
            return $this->validationErrorResponse([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $name = preg_replace('/\s+/', ' ', trim($atts['name']));
        if (strcasecmp($name, $user->name) === 0) {
            return $this->validationErrorResponse([
                'name' => ['Please provide a different name.'],
            ]);
        }

        if (! preg_match("/^(?!.*\s{2,})[A-Za-z][A-Za-z .'-]{0,48}[A-Za-z]$/", $name)) {
            return $this->validationErrorResponse([
                'name' => ['Name format is invalid. Use letters, spaces, apostrophes, dots, and hyphens only.'],
            ]);
        }

        $normalizedName = trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($name)));
        $blockedIdentityTerms = [
            'admin',
            'administrator',
            'support',
            'moderator',
            'staff',
            'official',
            'team',
            'security',
            'root',
            'system',
            'verified',
        ];
        $nameTokens = array_filter(explode(' ', $normalizedName));

        foreach ($nameTokens as $token) {
            if (in_array($token, $blockedIdentityTerms, true)) {
                return $this->validationErrorResponse([
                    'name' => ['This name is not allowed. Please choose a different display name.'],
                ]);
            }
        }

        $cooldownKey = "user:{$user->id}:name-change-cooldown";
        $cooldownUntil = Cache::get($cooldownKey);

        if ($cooldownUntil && now()->lt(Carbon::parse($cooldownUntil))) {
            return $this->validationErrorResponse([
                'name' => ['You can change your name again after ' . Carbon::parse($cooldownUntil)->toDateTimeString() . '.'],
            ], 'Name change is on cooldown');
        }

        $user->name = $name;
        $user->save();

        Cache::put($cooldownKey, now()->addDay()->toDateTimeString(), now()->addDay());

        return $this->successResponse([
            'name' => $user->name,
        ], 'Name updated successfully.');
    }


    public function follow(Request $request, string $username)
    {
        $actor = $request->user();
        $targetUser = User::findByUsername($username);

        if (! $targetUser) {
            return $this->notFoundResponse('User not found');
        }

        if ($actor->id === $targetUser->id) {
            return $this->validationErrorResponse([
                'user' => ['You cannot follow yourself.'],
            ]);
        }

        $created = DB::transaction(function () use ($actor, $targetUser) {
            $inserted = DB::table('follows')->insertOrIgnore([
                'follower_id' => $actor->id,
                'followed_id' => $targetUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted !== 1) {
                return false;
            }

            User::whereKey($actor->id)->increment('following_count');
            User::whereKey($targetUser->id)->increment('followers_count');

            return true;
        });

        $targetUser->refresh();

        if (! $created) {
            return $this->successResponse([
                'is_following' => true,
                'followers_count' => (int) $targetUser->followers_count,
            ], 'Already following user');
        }

        $targetUser->notify(new UserFollowedNotification($actor));

        $this->recommendationCacheService->bumpUserVersion($actor->id);

        Cache::forget("user:{$actor->id}:following_ids");
        Cache::forget("user:{$targetUser->id}:follower_ids");
        return $this->successResponse([
            'is_following' => true,
            'followers_count' => (int) $targetUser->followers_count,
        ], 'User followed successfully');
    }

    public function unfollow(Request $request, string $username)
    {
        $actor = $request->user();
        $targetUser = User::findByUsername($username);

        if (! $targetUser) {
            return $this->notFoundResponse('User not found');
        }

        $deleted = DB::transaction(function () use ($actor, $targetUser) {
            $deleted = DB::table('follows')
                ->where('follower_id', $actor->id)
                ->where('followed_id', $targetUser->id)
                ->delete();

            if ($deleted !== 1) {
                return false;
            }

            User::whereKey($actor->id)->where('following_count', '>', 0)->decrement('following_count');
            User::whereKey($targetUser->id)->where('followers_count', '>', 0)->decrement('followers_count');

            return true;
        });

        if (! $deleted) {
            return $this->notFoundResponse('You are not following this user');
        }

        $targetUser->refresh();
        $this->recommendationCacheService->bumpUserVersion($actor->id);

        Cache::forget("user:{$actor->id}:following_ids");
        Cache::forget("user:{$targetUser->id}:follower_ids");
        return $this->successResponse([
            'is_following' => false,
            'followers_count' => (int) $targetUser->followers_count,
        ], 'User unfollowed successfully');
    }
}
