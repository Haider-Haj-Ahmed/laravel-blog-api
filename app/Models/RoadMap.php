<?php

namespace App\Models;

use Database\Factories\RoadMapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoadMap extends Model
{
    /** @use HasFactory<RoadMapFactory> */
    use HasFactory;

    protected $guarded = [];
    public function nodes(){
        return $this->hasMany(Node::class);
    }
}
