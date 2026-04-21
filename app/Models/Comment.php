<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\ActivityService;
class Comment extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function (Comment $comment) {
            app(ActivityService::class)->purgeActivitiesForDeletedComment($comment);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    public function mentions()
    {
        return $this->belongsToMany(User::class, 'comment_user_mentions');
    }
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
    public function likes()
    {
        return $this->belongsToMany(User::class, 'comment_user_likes')->wherePivot('is_like',true)->withPivot('is_like')->withTimestamps();
    }
    public function dislikes(){
        return $this->belongsToMany(User::class, 'comment_user_likes')->wherePivot('is_like', false)->withPivot('is_like')->withTimestamps();
    }

    public function isLikedBy($user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->likes()->wherePivot('user_id', $user->id)->exists();
    }
}
