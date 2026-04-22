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
        $commentCounts = DB::table('comments')
            ->whereNotNull('post_id')
            ->select('post_id', DB::raw('COUNT(*) as total'))
            ->groupBy('post_id')
            ->pluck('total', 'post_id');

        $likeCounts = DB::table('likes')
            ->select('post_id', DB::raw('COUNT(*) as total'))
            ->groupBy('post_id')
            ->pluck('total', 'post_id');

        DB::table('posts')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($commentCounts, $likeCounts): void {
                foreach ($rows as $row) {
                    DB::table('posts')
                        ->where('id', $row->id)
                        ->update([
                            'comments_count' => (int) ($commentCounts[$row->id] ?? 0),
                            'likes_count' => (int) ($likeCounts[$row->id] ?? 0),
                        ]);
                }
            });
    }

    private function backfillBlogCounters(): void
    {
        $commentCounts = DB::table('comments')
            ->whereNotNull('blog_id')
            ->select('blog_id', DB::raw('COUNT(*) as total'))
            ->groupBy('blog_id')
            ->pluck('total', 'blog_id');

        $likeCounts = DB::table('blog_likes')
            ->select('blog_id', DB::raw('COUNT(*) as total'))
            ->groupBy('blog_id')
            ->pluck('total', 'blog_id');

        DB::table('blogs')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($commentCounts, $likeCounts): void {
                foreach ($rows as $row) {
                    DB::table('blogs')
                        ->where('id', $row->id)
                        ->update([
                            'comments_count' => (int) ($commentCounts[$row->id] ?? 0),
                            'likes_count' => (int) ($likeCounts[$row->id] ?? 0),
                        ]);
                }
            });
    }

    private function backfillViewCounterForTable(string $table, string $morphClass): void
    {
        $viewCounts = DB::table('views')
            ->select('viewable_id', DB::raw('COUNT(*) as total'))
            ->where('viewable_type', $morphClass)
            ->groupBy('viewable_id')
            ->pluck('total', 'viewable_id');

        DB::table($table)
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $viewCounts): void {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([
                            'views_count' => (int) ($viewCounts[$row->id] ?? 0),
                        ]);
                }
            });
    }
};
