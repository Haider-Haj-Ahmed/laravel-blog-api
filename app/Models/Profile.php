<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'avatar',
        'bio',
        'website',
        'location',
        'social_links',
        'cover_image',
        'settings',
        'ranking_points',
        'last_seen_at',
    ];

    protected $casts = [
        'social_links' => 'array',
        'settings' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Computed badge based on ranking points
    public function getBadgeAttribute()
    {
        $points = $this->ranking_points;
        if ($points >= 5000) {
            return 'expert';
        } elseif ($points >= 1000) {
            return 'senior';
        } else {
            return 'junior';
        }
    }

    // Check if user can publish blog articles
    public function canPublishBlog()
    {
        return $this->badge === 'expert';
    }
}
