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
            'order' => 1,
            'image_path' => null,
        ];
    }
}
