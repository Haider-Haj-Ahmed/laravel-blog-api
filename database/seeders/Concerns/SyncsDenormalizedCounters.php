<?php

namespace Database\Seeders\Concerns;

use App\Models\Blog;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

trait SyncsDenormalizedCounters
{
    protected function syncDenormalizedCounters(): void
    {
        DB::table('users')->update([
            'followers_count' => DB::raw(
                '(SELECT COUNT(*) FROM follows WHERE follows.followed_id = users.id)'
            ),
            'following_count' => DB::raw(
                '(SELECT COUNT(*) FROM follows WHERE follows.follower_id = users.id)'
            ),
            'published_posts_count' => DB::raw(
                '(SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id AND posts.is_published = 1)'
            ),
            'published_blogs_count' => DB::raw(
                '(SELECT COUNT(*) FROM blogs WHERE blogs.user_id = users.id AND blogs.is_published = 1)'
            ),
        ]);

        DB::table('posts')->update([
            'comments_count' => DB::raw('(SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id)'),
            'likes_count' => DB::raw('(SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id)'),
            'views_count' => DB::raw(
                '(SELECT COUNT(*) FROM views WHERE views.viewable_id = posts.id AND views.viewable_type = '.DB::getPdo()->quote((new Post)->getMorphClass()).')'
            ),
        ]);

        DB::table('blogs')->update([
            'comments_count' => DB::raw('(SELECT COUNT(*) FROM comments WHERE comments.blog_id = blogs.id)'),
            'likes_count' => DB::raw('(SELECT COUNT(*) FROM blog_likes WHERE blog_likes.blog_id = blogs.id)'),
            'views_count' => DB::raw(
                '(SELECT COUNT(*) FROM views WHERE views.viewable_id = blogs.id AND views.viewable_type = '.DB::getPdo()->quote((new Blog)->getMorphClass()).')'
            ),
        ]);

        DB::table('profiles')->update([
            'views_count' => DB::raw(
                '(SELECT COUNT(*) FROM views WHERE views.viewable_id = profiles.id AND views.viewable_type = '.DB::getPdo()->quote((new Profile)->getMorphClass()).')'
            ),
        ]);
    }
}
