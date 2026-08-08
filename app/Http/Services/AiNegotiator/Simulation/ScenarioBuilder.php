<?php

namespace App\Http\Services\AiNegotiator\Simulation;

use App\Http\Services\AiNegotiator\Support\LlmResponseParser;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Prompts\PromptTemplateService;
use RuntimeException;
use Throwable;

class ScenarioBuilder
{
    /**
     * @var list<string>
     */
    private const REQUIRED_FIELDS = [
        'name',
        'role',
        'title',
        'tone',
        'apparent_goal',
        'hidden_goal',
        'acceptable_limit',
        'pressure_points',
        'objection_style',
        'what_makes_soften',
        'what_makes_harden',
    ];

    public function __construct(
        protected PromptTemplateService $prompts,
        protected LlmResponseParser $parser,
    ) {}

    /**
     * @param  array<string, mixed>  $intakeData
     * @return array<string, mixed>
     */
    public function build(
        array $intakeData,
        string $difficulty,
        ?string $situationType,
        LlmProviderInterface $llm
    ): array {
        try {
            $systemPrompt = $this->prompts->buildScenarioBuilderPrompt(
                $intakeData,
                $difficulty,
                $situationType
            );

            // Claude Messages API requires a non-empty messages array.
            $response = $llm->chat(
                $systemPrompt,
                [['role' => 'user', 'content' => 'أنشئ الشخصية الآن كـ JSON فقط.']],
                ['max_tokens' => 1500]
            );

            $persona = $this->parser->parseJson($response->content);

            foreach (self::REQUIRED_FIELDS as $field) {
                if (!array_key_exists($field, $persona) || $persona[$field] === null || $persona[$field] === '') {
                    throw new RuntimeException("missing_persona_field:{$field}");
                }
            }

            return $persona;
        } catch (Throwable $e) {
            throw new RuntimeException('scenario_builder_failed', 0, $e);
        }
    }
}
