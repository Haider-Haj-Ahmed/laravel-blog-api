<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\CompilerService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class CompilerController extends Controller
{
    use ApiResponseTrait;

    public function run(Request $request, CompilerService $compiler)
    {
        $request->validate([
            "language" => "required|string|max:20",
            "code" => "required|string",
            "input" => "nullable|string"
        ]);

        $result = $compiler->run(
            $request->language,
            $request->code,
            $request->input ?? ""
        );

        return $this->successResponse(['result' => $result], 'Code compiled successfully');
    }
}
