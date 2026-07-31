<?php

namespace Database\Factories\Negotiation;

use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationSituation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NegotiationSituation>
 */
class NegotiationSituationFactory extends Factory
{
    protected $model = NegotiationSituation::class;

    public function definition(): array
    {
        return [
            'negotiation_level_id' => NegotiationLevel::factory(),
            'prompt_text' => fake()->sentence(8),
            'prompt_context' => fake()->optional()->sentence(6),
            'insight' => fake()->optional()->sentence(10),
            'prompt_type' => 'quote',
            'order_index' => null,
            'is_published' => false,
            'is_free' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => false,
        ]);
    }
}
