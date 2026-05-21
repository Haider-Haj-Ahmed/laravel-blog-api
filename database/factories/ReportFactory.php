<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        $post = Post::factory()->published()->create();

        return [
            'reporter_id' => User::factory()->verified(),
            'reportable_type' => $post->getMorphClass(),
            'reportable_id' => $post->id,
            'reason' => fake()->randomElement(['spam', 'harassment', 'misinformation', 'other']),
            'details' => fake()->optional()->sentence(),
            'status' => Report::STATUS_PENDING,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => Report::STATUS_PENDING,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'admin_notes' => null,
        ]);
    }
}
