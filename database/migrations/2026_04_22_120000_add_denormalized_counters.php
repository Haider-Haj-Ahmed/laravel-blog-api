<?php

use App\Models\Blog;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->unsignedBigInteger('following_count')->default(0);
            $table->unsignedBigInteger('published_posts_count')->default(0);
            $table->unsignedBigInteger('published_blogs_count')->default(0);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
        });

        Schema::table('blogs', function (Blueprint $table) {
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0);
        });

        $this->backfillUserCounters();
        $this->backfillPostCounters();
        $this->backfillBlogCounters();
        $this->backfillViewCounters();
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });

        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn(['comments_count', 'likes_count', 'views_count']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['comments_count', 'likes_count', 'views_count']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'followers_count',
                'following_count',
                'published_posts_count',
                'published_blogs_count',
            ]);
        });
    }

    private function backfillUserCounters(): void
    {
        DB::table('users')->update([
            'followers_count' => DB::raw(
                '(select count(*) from follows where follows.followed_id = users.id)'
            ),
            'following_count' => DB::raw(
                '(select count(*) from follows where follows.follower_id = users.id)'
            ),
            'published_posts_count' => DB::raw(
                '(select count(*) from posts where posts.user_id = users.id and posts.is_published = true)'
            ),
            'published_blogs_count' => DB::raw(
                '(select count(*) from blogs where blogs.user_id = users.id and blogs.is_published = true)'
            ),
        ]);
    }

    private function backfillViewCounters(): void
    {
        $this->backfillViewCounterForTable('posts', (new Post())->getMorphClass());
        $this->backfillViewCounterForTable('blogs', (new Blog())->getMorphClass());
        $this->backfillViewCounterForTable('profiles', (new Profile())->getMorphClass());
    }

    private function backfillPostCounters(): void
    {
        DB::table('posts')->update([
            'comments_count' => DB::raw('(SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id)'),
            'likes_count' => DB::raw('(SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id)'),
        ]);
    }

    private function backfillBlogCounters(): void
    {
        DB::table('blogs')->update([
            'comments_count' => DB::raw('(SELECT COUNT(*) FROM comments WHERE comments.blog_id = blogs.id)'),
            'likes_count' => DB::raw('(SELECT COUNT(*) FROM blog_likes WHERE blog_likes.blog_id = blogs.id)'),
        ]);
    }

    private function backfillViewCounterForTable(string $table, string $morphClass): void
    {
        $quotedMorphClass = DB::getPdo()->quote($morphClass);

        DB::table($table)->update([
            'views_count' => DB::raw("(SELECT COUNT(*) FROM views WHERE views.viewable_id = {$table}.id AND views.viewable_type = {$quotedMorphClass})"),
        ]);
    }
};
