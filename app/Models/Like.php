<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Post;

class Like extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'post_id'];
    public $timestamps = true;

    protected static function booted(): void
    {
        static::created(function (Like $like): void {
            Post::query()->whereKey($like->post_id)->increment('likes_count');
        });

        static::deleted(function (Like $like): void {
            Post::query()
                ->whereKey($like->post_id)
                ->where('likes_count', '>', 0)
                ->decrement('likes_count');
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
}
