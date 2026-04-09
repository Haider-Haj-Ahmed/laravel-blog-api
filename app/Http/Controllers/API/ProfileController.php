<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Profile;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\ProfileResource;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display the specified user's profile.
     */
    public function show($username)
    {
        $user = User::where('username', $username)
            ->with('profile')
            ->withCount([
                'followers',
                'following',
                'posts as published_posts_count' => fn ($query) => $query->where('is_published', true),
                'blogs as published_blogs_count' => fn ($query) => $query->where('is_published', true),
            ])
            ->first();

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        return $this->successResponse(new ProfileResource($user), 'Profile retrieved successfully');
    }
    public function showViaId($id){
        $profile = Profile::where('id', $id)->with('tags')->first();
        if(!$profile) {
            return response()->json(['message'=> 'Profile not found'],404);
        }
        return response()->json(['profile'=>$profile],200);
    }
    public function index(){
        $profiles = Profile::orderBy('created_at','desc')->paginate(10);
        return response()->json(['profiles'=>$profiles]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
            'bio' => 'nullable|string|max:500',
            'website' => 'nullable|url',
            'location' => 'nullable|string|max:100',
            'social_links' => 'nullable|array',
            'social_links.*' => 'url',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'settings' => 'nullable|array',
        ]);

        $profile = $user->profile;

        $this->authorize('update', $profile);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($profile->avatar && Storage::disk('public')->exists("avatars/{$profile->avatar}")) {
                Storage::disk('public')->delete("avatars/{$profile->avatar}");
            }

            $avatarFile = $request->file('avatar');
            $avatarName = time() . '_' . $user->id . '.' . $avatarFile->getClientOriginalExtension();
            $avatarFile->storeAs('avatars', $avatarName, 'public');
            $validated['avatar'] = $avatarName;
        } else {
            unset($validated['avatar']); // Don't update avatar if not provided
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            // Delete old cover if exists
            if ($profile->cover_image && Storage::disk('public')->exists("covers/{$profile->cover_image}")) {
                Storage::disk('public')->delete("covers/{$profile->cover_image}");
            }

            $coverFile = $request->file('cover_image');
            $coverName = time() . '_cover_' . $user->id . '.' . $coverFile->getClientOriginalExtension();
            $coverFile->storeAs('covers', $coverName, 'public');
            $validated['cover_image'] = $coverName;
        } else {
            unset($validated['cover_image']); // Don't update cover if not provided
        }

        $profile->fill($validated);
        $profile->save();

        $user->load('profile')->loadCount([
            'followers',
            'following',
            'posts as published_posts_count' => fn ($query) => $query->where('is_published', true),
            'blogs as published_blogs_count' => fn ($query) => $query->where('is_published', true),
        ]);

        return $this->successResponse(new ProfileResource($user), 'Profile updated successfully');
    }

    /**
     * Get user's posts.
     */
    public function posts($username)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        $posts = $user->posts()
            ->with('user')
            ->where('is_published', true)
            ->withCount('comments', 'likes')
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse(
            \App\Http\Resources\PostResource::collection($posts),
            'User posts retrieved successfully'
        );
    }

    /**
     * Get user's blogs.
     */
    public function blogs($username)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        $blogs = $user->blogs()
            ->with('user')
            ->where('is_published', true)
            ->withCount('comments', 'likes')
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse(
            \App\Http\Resources\BlogResource::collection($blogs),
            'User blogs retrieved successfully'
        );
    }
     public function viewrs(Request $request,$id){
        $profile = Profile::find($id);
        if (!$profile) {
            return $this->notFoundResponse('Profile not found');
        }
        return response()->json([
            'viewers' => $profile->views()->with('user')->get()->map(function ($view) {
                return [
                    'id' => $view->user_id,
                    'username' => $view->user->username,
                    'viewed_at' => $view->created_at->toDateTimeString(),
                ];
            }),
        ]);
    }
}
