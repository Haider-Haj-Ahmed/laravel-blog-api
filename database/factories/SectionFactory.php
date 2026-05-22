<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'blog_id' => Blog::factory()->published(),
            'title' => fake()->sentence(3),
            'content' => fake()->paragraphs(3, true),
            'order' => fake()->unique()->numberBetween(1, PHP_INT_MAX),
            'image_path' => null,
        ];
    }

    public function ordered(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }
}
