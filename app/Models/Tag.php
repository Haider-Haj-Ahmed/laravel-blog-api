<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $guarded = [];
    public function posts(){
        return $this->belongsToMany(Post::class,'post_tag');
    }
    public function profiles(){
        return $this->belongsToMany(Profile::class,'profile_tag');
    }
    public function blogs(){
        return $this->belongsToMany(Blog::class,'blog_tag');
    }
}
