<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->verified(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'code' => null,
            'code_language' => null,
            'is_published' => false,
            'is_modified' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    public function withCode(string $language = 'php'): static
    {
        return $this->state(fn () => [
            'code' => "<?php\n\necho 'Hello, world!';\n",
            'code_language' => $language,
        ]);
    }

}
