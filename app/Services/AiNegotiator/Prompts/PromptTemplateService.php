<?php

namespace App\Services\AiNegotiator\Prompts;

use RuntimeException;

class PromptTemplateService
{
    /**
     * System prompt for the Intake Agent.
     */
    public function buildIntakePrompt(): string
    {
        return $this->compose('intake.txt');
    }

    /**
     * System prompt used once to generate the opponent persona.
     *
     * @param  array<string, mixed>  $intakeData
     */
    public function buildScenarioBuilderPrompt(array $intakeData, string $difficulty, ?string $situationType): string
    {
        return $this->compose('scenario_builder.txt', [
            'intake_data_json' => $this->toJson($intakeData),
            'difficulty' => $difficulty,
            'situation_type' => $situationType ?? '',
        ]);
    }

    /**
     * System prompt for Opponent Simulation Agent.
     *
     * @param  array<string, mixed>  $intakeData
     * @param  array<string, mixed>  $opponentPersona
     */
    public function buildOpponentPrompt(
        array $intakeData,
        array $opponentPersona,
        string $difficulty,
        string $trainingMode
    ): string {
        return $this->compose('opponent_simulation.txt', [
            'opponent_persona_json' => $this->toJson($opponentPersona),
            'difficulty' => $difficulty,
            'training_mode' => $trainingMode,
            'intake_summary' => $this->toJson($intakeData),
        ]);
    }

    /**
     * System prompt for Evaluation Agent.
     *
     * @param  array<string, mixed>  $intakeData
     * @param  array<string, mixed>  $opponentPersona
     * @param  array<int, array<string, mixed>>  $transcript
     * @param  array<int, array<string, mixed>>  $rubricItems
     */
    public function buildEvaluationPrompt(
        array $intakeData,
        array $opponentPersona,
        array $transcript,
        array $rubricItems
    ): string {
        return $this->compose('evaluation.txt', [
            'intake_data_json' => $this->toJson($intakeData),
            'opponent_persona_json' => $this->toJson($opponentPersona),
            'transcript' => $this->formatTranscript($transcript),
            'rubric_items_json' => $this->toJson($rubricItems),
        ]);
    }

    /**
     * Fixed Arabic safety block prepended to every prompt.
     */
    private function getSafetyGuardrailsBlock(): string
    {
        return trim($this->loadTemplate('safety_guardrails.txt'));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function compose(string $templateFile, array $replacements = []): string
    {
        $body = $this->loadTemplate($templateFile);

        foreach ($replacements as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $this->getSafetyGuardrailsBlock() . "\n\n" . trim($body);
    }

    private function loadTemplate(string $filename): string
    {
        $path = resource_path('ai-negotiator/prompts/' . $filename);

        if (!is_file($path)) {
            throw new RuntimeException("AI Negotiator prompt template not found: {$filename}");
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException("AI Negotiator prompt template is empty: {$filename}");
        }

        return $contents;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function toJson(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    /**
     * @param  array<int, array<string, mixed>>  $transcript
     */
    private function formatTranscript(array $transcript): string
    {
        $lines = [];

        foreach ($transcript as $turn) {
            $role = (string) ($turn['role'] ?? 'unknown');
            $content = (string) ($turn['content'] ?? '');
            $lines[] = strtoupper($role) . ': ' . $content;
        }

        return $lines === [] ? '(لا توجد رسائل)' : implode("\n", $lines);
    }
}
