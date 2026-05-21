<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\Save;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Save>
 */
class SaveFactory extends Factory
{
    protected $model = Save::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->verified(),
        ];
    }

    public function forSaveable(Model $saveable): static
    {
        return $this->state(fn () => [
            'saveable_type' => $saveable->getMorphClass(),
            'saveable_id' => $saveable->getKey(),
        ]);
    }

    public function forPost(Post $post): static
    {
        return $this->forSaveable($post);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Save $save): void {
            if ($save->saveable_id) {
                return;
            }

            $post = Post::factory()->published()->create();
            $save->saveable_type = $post->getMorphClass();
            $save->saveable_id = $post->id;
        });
    }
}
