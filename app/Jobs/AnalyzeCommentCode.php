<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Events\CommentVerified;

class AnalyzeCommentCode implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Comment $comment)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(GeminiService $gemini): void
    {
        $result=$gemini->analyzeCode($this->comment->code);
        $rawText = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

        // Remove markdown fences
        $clean = preg_replace('/```[a-zA-Z]*\n?|\n?```/', '', $rawText);

        $parsed = json_decode($clean, true) ?? [];

        $label = strtoupper(trim($parsed['label'] ?? 'UNKNOWN'));

        $allowed = ['SAFE', 'UNSAFE', 'MALICIOUS', 'NONSENSE', 'UNKNOWN'];

        if (!in_array($label, $allowed)) {
            $label = 'UNKNOWN';
        }
        Log::error($label);
        $this->comment->code_label = $label;
        $this->comment->save();

        if ($label === 'SAFE') {
            CommentVerified::dispatch($this->comment);
        }
    }
}
