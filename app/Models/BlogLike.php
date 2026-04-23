<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogLike extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'blog_id'];

    protected static function booted(): void
    {
        static::created(function (BlogLike $blogLike): void {
            Blog::query()->whereKey($blogLike->blog_id)->increment('likes_count');
        });

        static::deleted(function (BlogLike $blogLike): void {
            Blog::query()
                ->whereKey($blogLike->blog_id)
                ->where('likes_count', '>', 0)
                ->decrement('likes_count');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }
}
