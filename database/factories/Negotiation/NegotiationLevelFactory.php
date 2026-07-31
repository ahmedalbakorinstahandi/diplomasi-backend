<?php

namespace Database\Factories\Negotiation;

use App\Models\Negotiation\NegotiationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NegotiationLevel>
 */
class NegotiationLevelFactory extends Factory
{
    protected $model = NegotiationLevel::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'subtitle' => fake()->optional()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'how_to_study' => fake()->optional()->paragraph(),
            'order_index' => null,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }
}
