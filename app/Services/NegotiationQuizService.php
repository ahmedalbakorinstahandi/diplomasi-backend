<?php

namespace App\Services;

use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use RuntimeException;

/**
 * Derived quiz generation + grading for the Negotiation Responses Library.
 * Pure / stateless: no DB writes. Attempt persistence is Task 4B.
 */
class NegotiationQuizService
{
    public const STYLES = ['gentle', 'diplomatic', 'firm'];

    public const QUICK_TEST_QUESTION_COUNT = 3;

    public const FINAL_TEST_QUESTION_COUNT = 15;

    /**
     * Build the per-situation quick test (exactly 3 questions, one per style).
     *
     * Return shape:
     * [
     *   'seed' => int,
     *   'questions' => [
     *     [
     *       'situation_id' => int,
     *       'asked_style' => 'gentle'|'diplomatic'|'firm',
     *       'correct_response_id' => int, // SERVER-SIDE ONLY — omit from client resources
     *       'options' => [
     *         ['id' => int, 'response_text' => string], // style omitted so clients cannot cheat
     *         ...
     *       ], // order is a deterministic shuffle of the 3 responses for $seed
     *     ],
     *     ...
     *   ],
     * ]
     *
     * Option order is randomized but STABLE for a given ($seed, situation_id, asked_style).
     * Persist $seed on the attempt (Task 4B) to reproduce the same order on review/replay.
     *
     * @throws RuntimeException if the situation does not have exactly one response per style
     */
    public function buildQuickTest(NegotiationSituation $situation, int $seed): array
    {
        $responsesByStyle = $this->loadResponsesByStyle($situation);

        $questions = [];
        foreach (self::STYLES as $askedStyle) {
            $questions[] = $this->buildQuestion($situation, $askedStyle, $responsesByStyle, $seed);
        }

        return [
            'seed' => $seed,
            'questions' => $questions,
        ];
    }

    /**
     * Build the per-level final light test (15 distinct situation×style pairs).
     *
     * Bank = every published situation in the level × 3 styles (up to 75).
     * Draw is a deterministic shuffle of the bank seeded by $seed, then take the first 15.
     *
     * Return shape: same as buildQuickTest (seed + questions[] with identical per-question shape).
     *
     * Not-enough-content policy: THROW RuntimeException if the bank has fewer than 15
     * valid questions. Returning a shorter test would break the fixed 15-question product
     * contract and scoreAttempt expectations; the codebase similarly rejects invalid content
     * state (RuntimeException in app/Services, 422 aborts in HTTP services).
     *
     * @throws RuntimeException if bank size < 15, or any drawn situation lacks 3 responses
     */
    public function buildFinalTest(NegotiationLevel $level, int $seed): array
    {
        $situations = NegotiationSituation::query()
            ->where('negotiation_level_id', $level->id)
            ->where('is_published', true)
            ->orderBy('order_index')
            ->orderBy('id')
            ->with('negotiationResponses')
            ->get();

        $bank = [];
        foreach ($situations as $situation) {
            // Validate each published situation has a full response set before drawing.
            $this->loadResponsesByStyle($situation);

            foreach (self::STYLES as $style) {
                $bank[] = [
                    'situation_id' => (int) $situation->id,
                    'asked_style' => $style,
                ];
            }
        }

        if (count($bank) < self::FINAL_TEST_QUESTION_COUNT) {
            throw new RuntimeException(
                'Negotiation final test requires at least '
                . self::FINAL_TEST_QUESTION_COUNT
                . ' questions in the level bank; found '
                . count($bank)
                . ' for negotiation_level_id='
                . $level->id
            );
        }

        $drawn = array_slice($this->seededShuffle($bank, $seed), 0, self::FINAL_TEST_QUESTION_COUNT);

        $situationsById = $situations->keyBy('id');
        $questions = [];

        foreach ($drawn as $pair) {
            $situation = $situationsById->get($pair['situation_id']);
            $responsesByStyle = $this->loadResponsesByStyle($situation);
            $questions[] = $this->buildQuestion(
                $situation,
                $pair['asked_style'],
                $responsesByStyle,
                $seed
            );
        }

        return [
            'seed' => $seed,
            'questions' => $questions,
        ];
    }

    /**
     * Pure grading. No writes.
     *
     * Return shape:
     * [
     *   'is_correct' => bool,
     *   'correct_response_id' => int,
     *   'selected_response_id' => int|null,
     *   'asked_style' => string,
     *   'feedback' => string, // correct response's explanation
     * ]
     *
     * @throws RuntimeException if situation lacks a complete response set or asked_style is invalid
     */
    public function gradeAnswer(
        NegotiationSituation $situation,
        string $askedStyle,
        ?int $selectedResponseId
    ): array {
        if (!in_array($askedStyle, self::STYLES, true)) {
            throw new RuntimeException("Invalid asked_style: {$askedStyle}");
        }

        $responsesByStyle = $this->loadResponsesByStyle($situation);
        $correct = $responsesByStyle[$askedStyle];

        $isCorrect = false;
        if ($selectedResponseId !== null) {
            $selected = $situation->negotiationResponses
                ->firstWhere('id', $selectedResponseId);

            if ($selected && $selected->style === $askedStyle) {
                $isCorrect = true;
            }
        }

        return [
            'is_correct' => $isCorrect,
            'correct_response_id' => (int) $correct->id,
            'selected_response_id' => $selectedResponseId,
            'asked_style' => $askedStyle,
            'feedback' => (string) $correct->explanation,
        ];
    }

    /**
     * Aggregate per-question grade results into attempt score.
     *
     * Scale: percentage 0–100 as decimal(6,2), matching DB score columns
     * (e.g. 2/3 correct → 66.67).
     *
     * Expects each item to have boolean key 'is_correct'.
     *
     * Return shape:
     * [
     *   'correct_count' => int,
     *   'total' => int,
     *   'score' => float, // 0–100, 2 decimal places
     * ]
     */
    public function scoreAttempt(array $answerResults): array
    {
        $total = count($answerResults);
        $correctCount = 0;

        foreach ($answerResults as $result) {
            if (!empty($result['is_correct'])) {
                $correctCount++;
            }
        }

        $score = $total > 0
            ? round(($correctCount / $total) * 100, 2)
            : 0.0;

        return [
            'correct_count' => $correctCount,
            'total' => $total,
            'score' => $score,
        ];
    }

    /**
     * @param  array<string, NegotiationResponse>  $responsesByStyle
     * @return array{
     *   situation_id: int,
     *   asked_style: string,
     *   correct_response_id: int,
     *   options: list<array{id: int, response_text: string}>
     * }
     */
    private function buildQuestion(
        NegotiationSituation $situation,
        string $askedStyle,
        array $responsesByStyle,
        int $seed
    ): array {
        $correct = $responsesByStyle[$askedStyle];

        $options = [];
        foreach ($responsesByStyle as $response) {
            $options[] = [
                'id' => (int) $response->id,
                'response_text' => (string) $response->response_text,
            ];
        }

        $optionSeed = $this->deriveOptionSeed($seed, (int) $situation->id, $askedStyle);
        $options = $this->seededShuffle($options, $optionSeed);

        return [
            'situation_id' => (int) $situation->id,
            'asked_style' => $askedStyle,
            'correct_response_id' => (int) $correct->id,
            'options' => $options,
        ];
    }

    /**
     * @return array<string, NegotiationResponse>
     *
     * @throws RuntimeException
     */
    private function loadResponsesByStyle(NegotiationSituation $situation): array
    {
        if (!$situation->relationLoaded('negotiationResponses')) {
            $situation->load('negotiationResponses');
        }

        $byStyle = [];
        foreach ($situation->negotiationResponses as $response) {
            if (!in_array($response->style, self::STYLES, true)) {
                throw new RuntimeException(
                    "Invalid response style '{$response->style}' on negotiation_response id={$response->id}"
                );
            }
            if (isset($byStyle[$response->style])) {
                throw new RuntimeException(
                    "Duplicate style '{$response->style}' for negotiation_situation_id={$situation->id}"
                );
            }
            $byStyle[$response->style] = $response;
        }

        foreach (self::STYLES as $style) {
            if (!isset($byStyle[$style])) {
                throw new RuntimeException(
                    "Negotiation situation id={$situation->id} is missing a '{$style}' response"
                );
            }
        }

        return $byStyle;
    }

    /**
     * Per-question option shuffle seed derived from attempt seed + situation + style,
     * so the same attempt reproduces the same option order on review.
     */
    private function deriveOptionSeed(int $seed, int $situationId, string $askedStyle): int
    {
        return crc32($seed . '|' . $situationId . '|' . $askedStyle);
    }

    /**
     * Deterministic Fisher–Yates shuffle using a local LCG (does not touch global mt_rand).
     *
     * @template T
     * @param  list<T>  $items
     * @return list<T>
     */
    private function seededShuffle(array $items, int $seed): array
    {
        $items = array_values($items);
        $n = count($items);
        if ($n <= 1) {
            return $items;
        }

        // Ensure unsigned 32-bit seed for the LCG.
        $state = $seed & 0x7fffffff;
        if ($state === 0) {
            $state = 1;
        }

        for ($i = $n - 1; $i > 0; $i--) {
            $state = ($state * 1103515245 + 12345) & 0x7fffffff;
            $j = $state % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }
}
