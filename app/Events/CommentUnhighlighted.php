<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentUnhighlighted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public $comment, public ?User $actor = null)
    {
    }
}
