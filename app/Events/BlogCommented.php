<?php

namespace App\Events;

use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BlogCommented
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Blog $blog,
        public Comment $comment,
        public ?User $actor = null,
    ) {
    }
}
