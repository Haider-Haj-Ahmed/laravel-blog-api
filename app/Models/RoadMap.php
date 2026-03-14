<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoadMap extends Model
{
    protected $guarded = [];
    public function nodes(){
        return $this->hasMany(Node::class);
    }
}
