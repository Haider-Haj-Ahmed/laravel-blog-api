<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuickSameUserPostsSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 1;
        $sharedTagName = 'software';

        $user = User::query()->find($userId);

        if (! $user) {
            $this->command?->warn("User with ID {$userId} was not found. Seeder skipped.");

            return;
        }

        $tag = Tag::query()->firstOrCreate(['name' => $sharedTagName]);

        Post::factory()
            ->count(50)
            ->create([
                'user_id' => $userId,
                'is_published' => true,
            ])
            ->each(function (Post $post) use ($tag): void {
                $post->tags()->syncWithoutDetaching([$tag->id]);
            });

        $this->command?->info("Seeded 50 posts for user ID {$userId} with shared tag '{$sharedTagName}'.");
    }
}