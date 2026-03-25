<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Save extends Model
{
    protected $fillable = [
        'user_id',
        'saveable_type',
        'saveable_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function saveable(): MorphTo
    {
        return $this->morphTo();
    }
}
