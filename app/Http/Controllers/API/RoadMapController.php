<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RoadMap;
use Illuminate\Http\Request;

class RoadMapController extends Controller
{
    public function index(){
        return response()->json(['data'=>RoadMap::all()],200);
    }
    public function show($id){
        $roadMap = RoadMap::with('nodes')->find($id);
        if(!$roadMap){
            return response()->json(['message'=>'RoadMap not found'],404);
        }
        return response()->json(['data'=>$roadMap],200);
    }
}
