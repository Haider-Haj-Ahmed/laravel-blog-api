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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

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
        return $this->successResponse(new UserSummaryResource($user),'your username has been successfuly updated our system will keep track of your previous username to redirect links to the new one');
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
            return $this->errorResponse('Failed to update email if you have been logged out after this message pleas call the support team', 500);
        }
            return $this->successResponse(null,'Email change requested. We sent an OTP to your new email. Your current email stays active until verification succeeds.');
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
