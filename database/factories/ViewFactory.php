<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use App\Models\View;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<View>
 */
class ViewFactory extends Factory
{
    protected $model = View::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->verified(),
        ];
    }

    public function forViewable(Model $viewable): static
    {
        return $this->state(fn () => [
            'viewable_type' => $viewable->getMorphClass(),
            'viewable_id' => $viewable->getKey(),
        ]);
    }

    public function forPost(Post $post): static
    {
        return $this->forViewable($post);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (View $view): void {
            if ($view->viewable_id) {
                return;
            }

            $post = Post::factory()->published()->create();
            $view->viewable_type = $post->getMorphClass();
            $view->viewable_id = $post->id;
        });
    }
}
