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
        'body',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Blog $blog) {
            $blog->saves()->delete();
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
}
