<?php

namespace App\Events;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BlogLiked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Blog $blog,
        public ?User $actor = null,
    ) {
    }
}
