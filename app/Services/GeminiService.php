<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    protected string $model = 'gemini-2.5-flash';
    protected string $endpoint = 'https://generativelanguage.googleapis.com/v1/models';
    public function analyzeCode(string $code)
    {
        $instructions = "
You are a code safety analyzer.
Explain what this code does in 1–2 sentences.
Then classify it using one of:
SAFE, UNSAFE, MALICIOUS, NONSENSE, UNKNOWN.

Return ONLY JSON in this format:

{
  \"explanation\": \"...\",
  \"label\": \"...\"
}
";
        $url = "{$this->endpoint}/{$this->model}:generateContent?key=" . config('services.gemini.key');

        $response = Http::post($url,[
            'contents' => [
                [
                    'parts' => [
                        ['text' => $instructions],
                        ['text' => $code]
                    ]
                ]
            ]
        ]);
        return $response->json();
    }
}
