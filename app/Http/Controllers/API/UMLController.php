<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\UMLService;
use Illuminate\Http\Request;

class UMLController extends Controller
{
    public function generate(Request $request,UMLService $service){
        $atts=$request->validate([
            'description'=>'required|string|max:500'
        ]);
        $result=$service->generateUML($atts['description']);
        return response($result)
            ->header('Content-Type', 'image/png');
    }
}
