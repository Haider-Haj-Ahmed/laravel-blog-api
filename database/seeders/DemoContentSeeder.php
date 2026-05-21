<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Blog;
use App\Models\BlogLike;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Report;
use App\Models\Save;
use App\Models\Section;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\View;
use Database\Seeders\Concerns\SyncsDenormalizedCounters;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    use SyncsDenormalizedCounters;

    /**
     * @var list<string>
     */
    private array $tagNames = [
        'php',
        'laravel',
        'javascript',
        'typescript',
        'react',
        'vue',
        'mysql',
        'api',
        'css',
        'devops',
        'security',
        'testing',
    ];

    public function run(): void
    {
        if (! $this->shouldSeed()) {
            $this->command?->info('Demo content seeding skipped (set SEED_DEMO=true or use local environment).');

            return;
        }

        $tags = $this->seedTags();
        $users = $this->seedUsers($tags);
        $demo = $users['demo'];

        if ($demo->posts()->exists() && ! filter_var(env('SEED_FORCE', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->info('Demo content already present. Set SEED_FORCE=true to re-seed posts and blogs.');

            return;
        }

        DB::transaction(function () use ($tags, $users): void {
            $this->seedFollows($users);
            $posts = $this->seedPosts($users, $tags);
            $this->seedBlogs($users, $tags);
            $this->seedEngagement($users, $posts);
            $this->seedModerationSamples($users);
        });

        $this->syncDenormalizedCounters();

        $this->command?->info('Demo content seeded successfully.');
        $this->printCredentials($users);
    }

    private function shouldSeed(): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        return filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, Tag>
     */
    private function seedTags(): array
    {
        $tags = [];

        foreach ($this->tagNames as $name) {
            $tags[$name] = Tag::query()->firstOrCreate(['name' => $name]);
        }

        return $tags;
    }

    /**
     * @param  array<string, Tag>  $tags
     * @return array<string, User>
     */
    private function seedUsers(array $tags): array
    {
        $password = env('SEED_DEMO_PASSWORD', 'password');

        $definitions = [
            'demo' => [
                'name' => 'Demo User',
                'email' => env('SEED_DEMO_EMAIL', 'demo@example.com'),
                'username' => env('SEED_DEMO_USERNAME', 'demo'),
                'bio' => 'Primary demo account for frontend development.',
                'ranking_points' => 850,
                'tags' => ['laravel', 'php', 'api'],
            ],
            'jane' => [
                'name' => 'Jane Developer',
                'email' => 'jane@example.com',
                'username' => 'jane_dev',
                'bio' => 'Full-stack developer sharing Laravel and React tips.',
                'ranking_points' => 2200,
                'tags' => ['react', 'typescript', 'laravel'],
            ],
            'bob' => [
                'name' => 'Bob Codes',
                'email' => 'bob@example.com',
                'username' => 'bob_codes',
                'bio' => 'Backend engineer focused on APIs and databases.',
                'ranking_points' => 1400,
                'tags' => ['php', 'mysql', 'api'],
            ],
            'alex' => [
                'name' => 'Alex Expert',
                'email' => 'alex@example.com',
                'username' => 'alex_expert',
                'bio' => 'Expert author publishing long-form blog articles.',
                'ranking_points' => 5600,
                'tags' => ['devops', 'security', 'testing'],
            ],
            'sam' => [
                'name' => 'Sam Viewer',
                'email' => 'sam@example.com',
                'username' => 'sam_viewer',
                'bio' => 'Reads, likes, and saves posts from the community.',
                'ranking_points' => 320,
                'tags' => ['javascript', 'css'],
            ],
        ];

        $users = [];

        foreach ($definitions as $key => $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'username' => $definition['username'],
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'is_admin' => false,
                ]
            );

            $profile = Profile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bio' => $definition['bio'],
                    'website' => 'https://example.com/'.$definition['username'],
                    'location' => 'Remote',
                    'ranking_points' => $definition['ranking_points'],
                    'settings' => [
                        'email_notifications' => true,
                        'push_notifications' => true,
                    ],
                    'last_seen_at' => now()->subHours(random_int(1, 48)),
                ]
            );

            $tagIds = Tag::query()
                ->whereIn('name', $definition['tags'])
                ->pluck('id')
                ->all();

            $profile->tags()->sync($tagIds);
            $users[$key] = $user->fresh(['profile']);
        }

        User::factory()
            ->verified()
            ->withProfile()
            ->count(36)
            ->create()
            ->each(function (User $user) use ($tags): void {
                $user->load('profile');
                $user->profile?->tags()->sync(
                    collect($tags)->random(random_int(2, 4))->pluck('id')->all()
                );
            });

        return $users;
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedFollows(array $users): void
    {
        $demo = $users['demo'];
        $targets = [$users['jane'], $users['bob'], $users['alex']];

        foreach ($targets as $target) {
            $demo->following()->syncWithoutDetaching([$target->id]);
        }

        $users['sam']->following()->syncWithoutDetaching([
            $users['demo']->id,
            $users['jane']->id,
        ]);

        $users['jane']->following()->syncWithoutDetaching([$users['bob']->id]);
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Tag>  $tags
     * @return list<Post>
     */
    private function seedPosts(array $users, array $tags): array
    {
        $posts = [];

        $demoPost = Post::factory()
            ->for($users['demo'])
            ->published()
            ->withCode('php')
            ->create([
                'title' => 'Building a REST API with Laravel',
                'body' => 'A walkthrough of routes, controllers, resources, and Sanctum authentication.',
            ]);
        $demoPost->tags()->sync([
            $tags['laravel']->id,
            $tags['php']->id,
            $tags['api']->id,
        ]);
        $posts[] = $demoPost;

        $janePost = Post::factory()
            ->for($users['jane'])
            ->published()
            ->create([
                'title' => 'React hooks patterns for data fetching',
                'body' => 'Practical patterns for loading, caching, and error states in React applications.',
            ]);
        $janePost->tags()->sync([
            $tags['react']->id,
            $tags['typescript']->id,
        ]);
        $posts[] = $janePost;

        $bobPost = Post::factory()
            ->for($users['bob'])
            ->published()
            ->withCode('sql')
            ->create([
                'title' => 'Indexing strategies for MySQL',
                'body' => 'How to choose indexes that speed up reads without hurting writes.',
                'code_language' => 'sql',
                'code' => "SELECT id, title FROM posts WHERE user_id = 1 ORDER BY created_at DESC LIMIT 20;",
            ]);
        $bobPost->tags()->sync([
            $tags['mysql']->id,
            $tags['php']->id,
        ]);
        $posts[] = $bobPost;

        Post::factory()
            ->for($users['demo'])
            ->draft()
            ->create([
                'title' => 'Draft: upcoming post about queues',
                'body' => 'Work in progress — not visible on the public feed.',
            ]);

        foreach ([$users['jane'], $users['bob'], $users['alex'], $users['sam']] as $author) {
            $created = Post::factory()
                ->for($author)
                ->published()
                ->count(18)
                ->create();

            foreach ($created as $post) {
                $post->tags()->sync(
                    collect($tags)->random(random_int(1, 3))->pluck('id')->all()
                );
                $posts[] = $post;
            }
        }

        return $posts;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Tag>  $tags
     */
    private function seedBlogs(array $users, array $tags): void
    {
        $alex = $users['alex'];

        $blog = Blog::factory()
            ->for($alex)
            ->published()
            ->create([
                'title' => 'Production-ready Laravel deployments',
                'subtitle' => 'From local Docker to CI/CD pipelines and zero-downtime releases.',
                'reading_time' => '12 min',
            ]);

        $blog->tags()->sync([
            $tags['laravel']->id,
            $tags['devops']->id,
        ]);

        $sections = [
            [
                'title' => 'Environment parity',
                'content' => 'Keep development, staging, and production as similar as possible.',
                'order' => 1,
            ],
            [
                'title' => 'Database migrations in CI',
                'content' => 'Run migrations in a controlled step before switching traffic.',
                'order' => 2,
            ],
            [
                'title' => 'Observability',
                'content' => 'Log aggregation, health checks, and alerting for production APIs.',
                'order' => 3,
            ],
        ];

        foreach ($sections as $section) {
            Section::query()->updateOrCreate(
                ['blog_id' => $blog->id, 'order' => $section['order']],
                [
                    'title' => $section['title'],
                    'content' => $section['content'],
                ]
            );
        }

        $secondBlog = Blog::factory()
            ->for($alex)
            ->published()
            ->create([
                'title' => 'Securing public APIs',
                'subtitle' => 'Rate limits, auth tokens, input validation, and abuse reporting.',
                'reading_time' => '9 min',
            ]);

        $secondBlog->tags()->sync([$tags['security']->id, $tags['api']->id]);

        Section::query()->updateOrCreate(
            ['blog_id' => $secondBlog->id, 'order' => 1],
            [
                'title' => 'Authentication layers',
                'content' => 'Sanctum tokens, expiration, and logout scopes.',
            ]
        );

        Blog::factory()
            ->for($alex)
            ->published()
            ->count(9)
            ->create()
            ->each(function (Blog $blog) use ($tags): void {
                $blog->tags()->sync(
                    collect($tags)->random(random_int(1, 3))->pluck('id')->all()
                );
            });

        Blog::factory()
            ->for($users['demo'])
            ->draft()
            ->create([
                'title' => 'Draft blog: frontend design system notes',
                'subtitle' => 'Unpublished article for testing the drafts endpoint.',
            ]);
    }

    /**
     * @param  array<string, User>  $users
     * @param  list<Post>  $posts
     */
    private function seedEngagement(array $users, array $posts): void
    {
        $demo = $users['demo'];
        $sam = $users['sam'];
        $jane = $users['jane'];

        $featuredPost = $posts[0];

        Comment::factory()
            ->forPost($featuredPost)
            ->for($sam)
            ->create(['body' => 'Great overview — this helped me wire up Sanctum quickly.']);

        $parentComment = Comment::factory()
            ->forPost($featuredPost)
            ->for($jane)
            ->create(['body' => 'Could you expand on API resource transformers?']);

        Comment::factory()
            ->forPost($featuredPost)
            ->for($demo)
            ->replyTo($parentComment)
            ->create(['body' => 'Sure — I will add a follow-up section in the next revision.']);

        Comment::factory()
            ->forPost($featuredPost)
            ->count(18)
            ->create();

        foreach (array_slice($posts, 0, 24) as $post) {
            Comment::factory()
                ->forPost($post)
                ->count(6)
                ->create();
        }

        foreach (array_slice($posts, 0, 36) as $post) {
            Like::query()->firstOrCreate([
                'user_id' => $sam->id,
                'post_id' => $post->id,
            ]);

            if ($post->user_id !== $demo->id) {
                Like::query()->firstOrCreate([
                    'user_id' => $demo->id,
                    'post_id' => $post->id,
                ]);
            }

            View::query()->firstOrCreate([
                'user_id' => $sam->id,
                'viewable_type' => $post->getMorphClass(),
                'viewable_id' => $post->id,
            ]);

            Save::query()->firstOrCreate([
                'user_id' => $demo->id,
                'saveable_type' => $post->getMorphClass(),
                'saveable_id' => $post->id,
            ]);
        }

        $publishedBlog = Blog::query()->where('is_published', true)->first();

        if ($publishedBlog) {
            Comment::factory()
                ->forBlog($publishedBlog)
                ->for($demo)
                ->create(['body' => 'Excellent deployment checklist — saving this for our next release.']);

            Comment::factory()
                ->forBlog($publishedBlog)
                ->count(9)
                ->create();

            BlogLike::query()->firstOrCreate([
                'user_id' => $sam->id,
                'blog_id' => $publishedBlog->id,
            ]);

            View::query()->firstOrCreate([
                'user_id' => $demo->id,
                'viewable_type' => $publishedBlog->getMorphClass(),
                'viewable_id' => $publishedBlog->id,
            ]);
        }

        Activity::query()->create([
            'owner_user_id' => $featuredPost->user_id,
            'actor_user_id' => $sam->id,
            'action' => 'post_liked',
            'subject_type' => $featuredPost->getMorphClass(),
            'subject_id' => $featuredPost->id,
        ]);

        Activity::query()->create([
            'owner_user_id' => $featuredPost->user_id,
            'actor_user_id' => $jane->id,
            'action' => 'post_commented',
            'subject_type' => $featuredPost->getMorphClass(),
            'subject_id' => $featuredPost->id,
            'meta' => ['comment_id' => $parentComment->id],
        ]);

        $demoProfile = $demo->profile;

        if ($demoProfile) {
            View::query()->firstOrCreate([
                'user_id' => $sam->id,
                'viewable_type' => $demoProfile->getMorphClass(),
                'viewable_id' => $demoProfile->id,
            ]);
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedModerationSamples(array $users): void
    {
        $spammy = User::factory()->verified()->create([
            'name' => 'Spam Account',
            'username' => 'spam_account_'.Str::lower(Str::random(4)),
            'email' => 'spam_'.Str::lower(Str::random(6)).'@example.com',
        ]);

        UserBlock::query()->firstOrCreate([
            'user_id' => $users['demo']->id,
            'blocked_user_id' => $spammy->id,
        ]);

        $targetPost = Post::query()->where('is_published', true)->where('user_id', '!=', $users['demo']->id)->first();

        if ($targetPost) {
            Report::query()->firstOrCreate(
                [
                    'reporter_id' => $users['sam']->id,
                    'reportable_type' => $targetPost->getMorphClass(),
                    'reportable_id' => $targetPost->id,
                ],
                [
                    'reason' => 'spam',
                    'details' => 'Seeded sample report for moderation UI.',
                    'status' => Report::STATUS_PENDING,
                ]
            );
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function printCredentials(array $users): void
    {
        $password = env('SEED_DEMO_PASSWORD', 'password');

        $this->command?->newLine();
        $this->command?->info('Frontend demo accounts (password: '.$password.'):');

        foreach (['demo', 'jane', 'bob', 'alex', 'sam'] as $key) {
            $user = $users[$key];
            $this->command?->line("  - {$user->email} (@{$user->username})");
        }
    }
}
