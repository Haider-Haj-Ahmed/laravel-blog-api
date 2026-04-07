<?php

namespace App\Events;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostCommented
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Post $post,
        public Comment $comment,
        public ?User $actor = null,
    ) {
    }
}
