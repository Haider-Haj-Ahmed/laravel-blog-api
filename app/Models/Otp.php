<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use \App\Models\User;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'code', 'channel', 'expires_at', 'attempts'];

    // Eloquent casts ensure expires_at is a Carbon instance so date helpers work
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        // If expires_at is null or not set, consider it expired by default
        if (empty($this->expires_at)) {
            return true;
        }

        // Cast to Carbon if it's not already one
        $expiresAt = $this->expires_at instanceof Carbon ? $this->expires_at : Carbon::parse($this->expires_at);
        return $expiresAt->lt(now());
    }
}