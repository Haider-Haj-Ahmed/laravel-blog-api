<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $guarded = [];
    public function roadMap(){
        return $this->belongsTo(RoadMap::class);
    }
}
