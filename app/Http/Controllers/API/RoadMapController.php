<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RoadMap;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class RoadMapController extends Controller
{
    use ApiResponseTrait;

    public function index(){
        return $this->successResponse(RoadMap::all(), 'Road maps retrieved successfully');
    }
    public function show($id){
        $roadMap = RoadMap::with('nodes')->find($id);
        if(!$roadMap){
            return $this->notFoundResponse('RoadMap not found');
        }
        return $this->successResponse($roadMap, 'Road map retrieved successfully');
    }
}
