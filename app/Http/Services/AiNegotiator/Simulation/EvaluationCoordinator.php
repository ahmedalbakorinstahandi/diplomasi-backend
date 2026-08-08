<?php

namespace App\Http\Services\AiNegotiator\Simulation;

use App\Http\Services\AiNegotiator\MessageService;
use App\Http\Services\AiNegotiator\Support\LlmResponseParser;
use App\Models\AiNegotiator\AiNegotiatorEvaluation;
use App\Models\AiNegotiator\AiNegotiatorEvaluationScore;
use App\Models\AiNegotiator\AiNegotiatorRubricItem;
use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Prompts\PromptTemplateService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class EvaluationCoordinator
{
    /**
     * @var list<string>
     */
    private const REQUIRED_KEYS = [
        'overall_score',
        'summary',
        'best_line',
        'weakest_line',
        'biggest_mistake',
        'quick_concession',
        'sensitive_info_leaked',
        'good_questions',
        'suggested_alternative_response',
        'retry_exercise',
        'suggested_next_difficulty',
        'rubric_scores',
    ];

    public function __construct(
        protected MessageService $messages,
        protected PromptTemplateService $prompts,
        protected LlmResponseParser $parser,
    ) {}

    public function generate(
        AiNegotiatorSession $session,
        LlmProviderInterface $llm
    ): AiNegotiatorEvaluation {
        try {
            $transcript = $this->messages->getSessionTranscript($session, 'simulating');
            $rubricItems = AiNegotiatorRubricItem::query()
                ->where('is_published', true)
                ->orderBy('order_index')
                ->get()
                ->map(static function (AiNegotiatorRubricItem $item): array {
                    return [
                        'code' => $item->code,
                        'title' => $item->title,
                        'description' => $item->description,
                        'weight' => (int) $item->weight,
                    ];
                })
                ->all();

            $systemPrompt = $this->prompts->buildEvaluationPrompt(
                is_array($session->intake_data) ? $session->intake_data : [],
                is_array($session->opponent_persona) ? $session->opponent_persona : [],
                $transcript,
                $rubricItems
            );

            // Claude Messages API requires a non-empty messages array.
            $response = $llm->chat(
                $systemPrompt,
                [['role' => 'user', 'content' => 'أنتج تقرير التقييم كـ JSON فقط.']],
                ['max_tokens' => 3000]
            );

            $payload = $this->parser->parseJson($response->content);
            $this->assertPayload($payload);

            return DB::transaction(function () use ($session, $payload, $rubricItems) {
                $evaluation = AiNegotiatorEvaluation::create([
                    'ai_negotiator_session_id' => $session->id,
                    'overall_score' => (int) $payload['overall_score'],
                    'summary' => (string) $payload['summary'],
                    'best_line' => $payload['best_line'] ?? null,
                    'weakest_line' => $payload['weakest_line'] ?? null,
                    'biggest_mistake' => $payload['biggest_mistake'] ?? null,
                    'quick_concession' => (bool) $payload['quick_concession'],
                    'sensitive_info_leaked' => (bool) $payload['sensitive_info_leaked'],
                    'good_questions' => (bool) $payload['good_questions'],
                    'suggested_alternative_response' => $payload['suggested_alternative_response'] ?? null,
                    'retry_exercise' => $payload['retry_exercise'] ?? null,
                    'suggested_next_difficulty' => $payload['suggested_next_difficulty'] ?? null,
                ]);

                $weightsByCode = collect($rubricItems)->keyBy('code');
                $rubricByCode = AiNegotiatorRubricItem::query()
                    ->whereIn('code', $weightsByCode->keys()->all())
                    ->get()
                    ->keyBy('code');

                foreach ($payload['rubric_scores'] as $scoreRow) {
                    $code = (string) ($scoreRow['code'] ?? '');
                    $item = $rubricByCode->get($code);
                    if (!$item) {
                        throw new RuntimeException("unknown_rubric_code:{$code}");
                    }

                    AiNegotiatorEvaluationScore::create([
                        'ai_negotiator_evaluation_id' => $evaluation->id,
                        'ai_negotiator_rubric_item_id' => $item->id,
                        'score' => (int) ($scoreRow['score'] ?? 0),
                        'max_score' => (int) $item->weight,
                    ]);
                }

                return $evaluation->load('scores');
            });
        } catch (Throwable $e) {
            throw new RuntimeException('evaluation_generation_failed', 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertPayload(array $payload): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new RuntimeException("missing_evaluation_key:{$key}");
            }
        }

        if (!is_array($payload['rubric_scores']) || count($payload['rubric_scores']) !== 8) {
            throw new RuntimeException('invalid_rubric_scores');
        }
    }
}
