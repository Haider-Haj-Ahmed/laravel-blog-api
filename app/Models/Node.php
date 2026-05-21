<?php

namespace App\Models;

use Database\Factories\NodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory;

    protected $guarded = [];
    public function roadMap(){
        return $this->belongsTo(RoadMap::class);
    }
}
