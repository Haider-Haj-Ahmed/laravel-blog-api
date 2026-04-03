<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'path',
        'sort_order',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
