<?php

namespace Database\Factories\Negotiation;

use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NegotiationResponse>
 */
class NegotiationResponseFactory extends Factory
{
    protected $model = NegotiationResponse::class;

    public function definition(): array
    {
        return [
            'negotiation_situation_id' => NegotiationSituation::factory(),
            'style' => 'gentle',
            'response_text' => fake()->sentence(10),
            'explanation' => fake()->sentence(12),
        ];
    }

    public function style(string $style): static
    {
        return $this->state(fn (array $attributes) => [
            'style' => $style,
        ]);
    }
}
