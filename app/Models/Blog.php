<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Blog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'subtitle',
        'reading_time',
        'cover_image_path',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'comments_count' => 'integer',
        'likes_count' => 'integer',
        'views_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Blog $blog) {
            $blog->saves()->delete();
            $blog->views()->delete();
        });
    }

    public function saves(): MorphMany
    {
        return $this->morphMany(Save::class, 'saveable');
    }

    public function views(): MorphMany
    {
        return $this->morphMany(View::class, 'viewable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function tags(){
        return $this->belongsToMany(Tag::class,'blog_tag');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->hasMany(BlogLike::class);
    }

    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'blog_likes')->withTimestamps();
    }

    public function isLikedBy($user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->likedByUsers()->where('user_id', $user->id)->exists();
    }
    public function sections()
    {
        return $this->hasMany(Section::class);
    }
}
