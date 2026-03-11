<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DeepSeekService
{
    protected $endpoint = 'https://api.deepseek.com/v1/chat/completions';

    public function analyzeCode(string $code)
    {
        $prompt = "
You are a code safety analyzer.
Explain what this code does in 1–2 sentences.
Then classify it using one word from this list:
SAFE, UNSAFE, MALICIOUS, NONSENSE, UNKNOWN.
Return ONLY the explanation and the label in JSON:

{
  \"explanation\": \"...\",
  \"label\": \"...\"
}

Code:
$code
";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.deepseek.key'),
            'Content-Type' => 'application/json',
        ])->post($this->endpoint, [
            'model' => 'deepseek-coder-v2',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.2,
        ]);

        return $response->json();
    }
}
