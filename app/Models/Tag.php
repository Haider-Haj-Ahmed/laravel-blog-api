<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $guarded = [];
    public function posts(){
        return $this->belongsToMany(Post::class,'post_tag');
    }
    public function profiles(){
        return $this->belongsToMany(Profile::class,'profile_tag');
    }
}
