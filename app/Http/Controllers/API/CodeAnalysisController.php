<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DeepSeekService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CodeAnalysisController extends Controller
{
    // public function analyze(Request $request, DeepSeekService $deepseek)
    // {
    //     $request->validate([
    //         'code' => 'required|string'
    //     ]);

    //     $result = $deepseek->analyzeCode($request->code);
    //     Log::error($result);

    //     // Extract the model's JSON output
    //     $content = $result['choices'][0]['message']['content'] ?? null;

    //     // Try to decode JSON
    //     $parsed = json_decode($content, true);

    //     return response()->json([
    //         'raw' => $result,
    //         'parsed' => $parsed,
    //     ]);
    // }
    public function analyze(Request $request, GeminiService $gemini)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $result = $gemini->analyzeCode($request->code);

        // Extract the text Gemini returned
        $rawText = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

        // Remove Markdown code fences like ```json ... ```
        $clean = preg_replace('/```[a-zA-Z]*\n?|\n?```/', '', $rawText);

        // Decode JSON safely
        $parsed = json_decode($clean, true) ?? [];

        // Extract label
        $label = strtoupper(trim($parsed['label'] ?? 'UNKNOWN'));

        $allowed = ['SAFE', 'UNSAFE', 'MALICIOUS', 'NONSENSE', 'UNKNOWN'];

        if (!in_array($label, $allowed)) {
            $label = 'UNKNOWN';
        }

        return response()->json([
            'label' => $label,
            'explanation' => $parsed['explanation'] ?? null,
            'raw' => $result
        ]);
    }
}
