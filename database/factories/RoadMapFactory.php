<?php

namespace Database\Factories;

use App\Models\RoadMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoadMap>
 */
class RoadMapFactory extends Factory
{
    protected $model = RoadMap::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(3, true),
            'description' => fake()->paragraph(),
        ];
    }
}
