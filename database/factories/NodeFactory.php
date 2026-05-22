<?php

namespace Database\Factories;

use App\Models\Node;
use App\Models\RoadMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Node>
 */
class NodeFactory extends Factory
{
    protected $model = Node::class;

    public function definition(): array
    {
        return [
            'road_map_id' => RoadMap::factory(),
            'step_number' => fake()->numberBetween(1, 20),
            'title' => fake()->sentence(4),
            'url' => fake()->url(),
        ];
    }
}
