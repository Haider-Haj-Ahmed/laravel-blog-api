<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'body', 'code', 'code_language', 'photo', 'is_published'];

    protected static function booted(): void
    {
        static::deleting(function (Post $post) {
            $post->saves()->delete();
        });
    }

    public function saves(): MorphMany
    {
        return $this->morphMany(Save::class, 'saveable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'likes')->withTimestamps();
    }

    public function isLikedBy($user)
    {
        if (!$user) {
            return false;
        }
        return $this->likedByUsers()->where('user_id', $user->id)->exists();
    }
    public function tags(){
        return $this->belongsToMany(Tag::class,'post_tag');
    }
}
