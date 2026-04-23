<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\UsernameMap;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function likedPosts()
    {
        return $this->belongsToMany(Post::class, 'likes')->withTimestamps();
    }

    public function likedBlogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_likes')->withTimestamps();
    }
    public function comments(){
        return $this->hasMany(Comment::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')->withTimestamps();
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')->withTimestamps();
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->where('followed_id', $user->id)->exists();
    }

    public function saves()
    {
        return $this->hasMany(Save::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'owner_user_id');
    }

    /**
     * Find user by username with fallback to username mappings.
     * 
     * @param string $username The username to search for
     * @return \App\Models\User|null
     */
    public static function findByUsername(string $username)
    {
        // Try direct lookup first (fast path)
        $user = self::where('username', $username)->first();
        
        if ($user) {
            return $user;
        }
        
        // Fall back to old username mapping
        $mapping = UsernameMap::where('old', $username)->latest()->first();
        
        if ($mapping) {
            return self::where('username', $mapping->current)->first();
        }
        
        return null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable = [
        'name',
        'email',
        'pending_email',
        'password',
        'username',
        'phone',
        'phone_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
        'followers_count' => 'integer',
        'following_count' => 'integer',
        'published_posts_count' => 'integer',
        'published_blogs_count' => 'integer',
    ];
}
