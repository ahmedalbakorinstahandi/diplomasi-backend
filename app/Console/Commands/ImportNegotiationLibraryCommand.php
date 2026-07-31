<?php

namespace App\Console\Commands;

use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use App\Services\NegotiationQuizService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ImportNegotiationLibraryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'negotiation:import
                            {path=database/data/negotiation_library_content.json : Path to the content JSON file}
                            {--publish : Publish levels/situations that pass the integrity check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Negotiation Responses Library content from JSON (unpublished by default)';

    public function handle(): int
    {
        $path = $this->argument('path');
        $absolute = $this->resolvePath($path);
        $shouldPublish = (bool) $this->option('publish');

        if (!is_file($absolute)) {
            $this->error("File not found: {$absolute}");

            return self::FAILURE;
        }

        $raw = file_get_contents($absolute);
        $payload = json_decode($raw, true);

        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Malformed JSON: ' . json_last_error_msg());

            return self::FAILURE;
        }

        if (!isset($payload['levels']) || !is_array($payload['levels'])) {
            $this->error('JSON must contain a top-level "levels" array.');

            return self::FAILURE;
        }

        $report = [];

        try {
            DB::transaction(function () use ($payload, $shouldPublish, &$report) {
                foreach ($payload['levels'] as $levelIndex => $levelData) {
                    $report[] = $this->importLevel($levelData, $levelIndex, $shouldPublish);
                }
            });
        } catch (Throwable $e) {
            $this->error('Import rolled back: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->printReport($report, $shouldPublish);

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  array<string, mixed>  $levelData
     * @return array<string, mixed>
     */
    private function importLevel(array $levelData, int $levelIndex, bool $shouldPublish): array
    {
        $orderIndex = $levelData['order_index'] ?? null;
        if ($orderIndex === null) {
            throw new RuntimeException("levels[{$levelIndex}]: missing order_index");
        }

        foreach (['title'] as $required) {
            if (!isset($levelData[$required]) || $levelData[$required] === '') {
                throw new RuntimeException("levels[{$levelIndex}]: missing required field '{$required}'");
            }
        }

        $level = NegotiationLevel::withTrashed()->updateOrCreate(
            ['order_index' => (int) $orderIndex],
            [
                'title' => (string) $levelData['title'],
                'subtitle' => $levelData['subtitle'] ?? null,
                'description' => $levelData['description'] ?? null,
                'how_to_study' => $levelData['how_to_study'] ?? null,
                'is_published' => false,
                'deleted_at' => null,
            ]
        );

        $levelWasRecentlyCreated = $level->wasRecentlyCreated;

        $situationsData = $levelData['situations'] ?? null;
        if (!is_array($situationsData)) {
            throw new RuntimeException("levels[{$levelIndex}]: situations must be an array");
        }

        $stats = [
            'order_index' => (int) $orderIndex,
            'title' => (string) $levelData['title'],
            'level_created' => $levelWasRecentlyCreated,
            'level_updated' => !$levelWasRecentlyCreated,
            'situations_created' => 0,
            'situations_updated' => 0,
            'responses_created' => 0,
            'responses_updated' => 0,
            'integrity_passed' => 0,
            'integrity_failed' => 0,
            'situations_published' => 0,
            'withheld' => [],
        ];

        $allSituationsPass = true;

        foreach ($situationsData as $situationIndex => $situationData) {
            if (!is_array($situationData)) {
                throw new RuntimeException("levels[{$levelIndex}].situations[{$situationIndex}]: must be an object");
            }

            $situationStats = $this->importSituation(
                $level,
                $situationData,
                $levelIndex,
                $situationIndex,
                $shouldPublish
            );

            $stats['situations_created'] += $situationStats['created'] ? 1 : 0;
            $stats['situations_updated'] += $situationStats['created'] ? 0 : 1;
            $stats['responses_created'] += $situationStats['responses_created'];
            $stats['responses_updated'] += $situationStats['responses_updated'];

            if ($situationStats['integrity_ok']) {
                $stats['integrity_passed']++;
                if ($situationStats['published']) {
                    $stats['situations_published']++;
                }
            } else {
                $allSituationsPass = false;
                $stats['integrity_failed']++;
                $stats['withheld'][] = $situationStats['withheld_reason'];
            }
        }

        // Publish level only when every situation passes integrity and --publish was set.
        $levelPublished = false;
        if ($shouldPublish && $allSituationsPass && count($situationsData) > 0) {
            $level->is_published = true;
            $level->save();
            $levelPublished = true;
        } else {
            $level->is_published = false;
            $level->save();
        }

        $stats['level_published'] = $levelPublished;

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $situationData
     * @return array<string, mixed>
     */
    private function importSituation(
        NegotiationLevel $level,
        array $situationData,
        int $levelIndex,
        int $situationIndex,
        bool $shouldPublish
    ): array {
        $orderIndex = $situationData['order_index'] ?? null;
        if ($orderIndex === null) {
            throw new RuntimeException(
                "levels[{$levelIndex}].situations[{$situationIndex}]: missing order_index"
            );
        }

        if (!isset($situationData['prompt_text']) || $situationData['prompt_text'] === '') {
            throw new RuntimeException(
                "levels[{$levelIndex}].situations[{$situationIndex}]: missing prompt_text"
            );
        }

        $promptType = $situationData['prompt_type'] ?? 'quote';
        if (!in_array($promptType, ['quote', 'scene'], true)) {
            throw new RuntimeException(
                "levels[{$levelIndex}].situations[{$situationIndex}]: invalid prompt_type '{$promptType}'"
            );
        }

        if (!array_key_exists('is_free', $situationData)) {
            throw new RuntimeException(
                "levels[{$levelIndex}].situations[{$situationIndex}]: missing is_free"
            );
        }

        $responsesData = $situationData['responses'] ?? null;
        if (!is_array($responsesData)) {
            throw new RuntimeException(
                "levels[{$levelIndex}].situations[{$situationIndex}]: responses must be an array"
            );
        }

        $situation = NegotiationSituation::withTrashed()->updateOrCreate(
            [
                'negotiation_level_id' => $level->id,
                'order_index' => (int) $orderIndex,
            ],
            [
                'prompt_text' => (string) $situationData['prompt_text'],
                'prompt_context' => $situationData['prompt_context'] ?? null,
                'insight' => $situationData['insight'] ?? null,
                'prompt_type' => $promptType,
                'is_free' => (bool) $situationData['is_free'],
                'is_published' => false,
                'deleted_at' => null,
            ]
        );

        $created = $situation->wasRecentlyCreated;
        $responsesCreated = 0;
        $responsesUpdated = 0;

        foreach ($responsesData as $responseIndex => $responseData) {
            if (!is_array($responseData)) {
                throw new RuntimeException(
                    "levels[{$levelIndex}].situations[{$situationIndex}].responses[{$responseIndex}]: must be an object"
                );
            }

            $style = $responseData['style'] ?? null;
            if (!in_array($style, NegotiationQuizService::STYLES, true)) {
                throw new RuntimeException(
                    "levels[{$levelIndex}].situations[{$situationIndex}].responses[{$responseIndex}]: unknown style '{$style}'"
                );
            }

            if (!isset($responseData['response_text']) || !isset($responseData['explanation'])) {
                throw new RuntimeException(
                    "levels[{$levelIndex}].situations[{$situationIndex}].responses[{$responseIndex}]: missing response_text or explanation"
                );
            }

            $response = NegotiationResponse::withTrashed()->updateOrCreate(
                [
                    'negotiation_situation_id' => $situation->id,
                    'style' => $style,
                ],
                [
                    'response_text' => (string) $responseData['response_text'],
                    'explanation' => (string) $responseData['explanation'],
                    'deleted_at' => null,
                ]
            );

            if ($response->wasRecentlyCreated) {
                $responsesCreated++;
            } else {
                $responsesUpdated++;
            }
        }

        $situation->load('negotiationResponses');
        $integrity = $this->checkSituationIntegrity($situation);

        $published = false;
        if ($shouldPublish && $integrity['ok']) {
            $situation->is_published = true;
            $situation->save();
            $published = true;
        } else {
            $situation->is_published = false;
            $situation->save();
        }

        return [
            'created' => $created,
            'responses_created' => $responsesCreated,
            'responses_updated' => $responsesUpdated,
            'integrity_ok' => $integrity['ok'],
            'published' => $published,
            'withheld_reason' => $integrity['ok']
                ? null
                : "L{$level->order_index} S{$orderIndex}: {$integrity['reason']}",
        ];
    }

    /**
     * @return array{ok: bool, reason: string|null}
     */
    private function checkSituationIntegrity(NegotiationSituation $situation): array
    {
        $responses = $situation->negotiationResponses;
        if ($responses->count() !== 3) {
            return [
                'ok' => false,
                'reason' => 'expected exactly 3 responses, found ' . $responses->count(),
            ];
        }

        $byStyle = [];
        foreach ($responses as $response) {
            if (!in_array($response->style, NegotiationQuizService::STYLES, true)) {
                return ['ok' => false, 'reason' => "invalid style '{$response->style}'"];
            }
            if (isset($byStyle[$response->style])) {
                return ['ok' => false, 'reason' => "duplicate style '{$response->style}'"];
            }
            if (trim((string) $response->response_text) === '') {
                return ['ok' => false, 'reason' => "empty response_text for style '{$response->style}'"];
            }
            if (trim((string) $response->explanation) === '') {
                return ['ok' => false, 'reason' => "empty explanation for style '{$response->style}'"];
            }
            $byStyle[$response->style] = true;
        }

        foreach (NegotiationQuizService::STYLES as $style) {
            if (!isset($byStyle[$style])) {
                return ['ok' => false, 'reason' => "missing style '{$style}'"];
            }
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $report
     */
    private function printReport(array $report, bool $shouldPublish): void
    {
        $this->info('Negotiation library import complete' . ($shouldPublish ? ' (--publish)' : ' (unpublished)'));
        $this->newLine();

        $totalLevels = count($report);
        $totalSituations = 0;
        $totalResponses = 0;
        $totalFree = 0;
        $totalPublishedSituations = 0;
        $totalPassed = 0;
        $totalFailed = 0;

        foreach ($report as $levelReport) {
            $totalSituations += $levelReport['situations_created'] + $levelReport['situations_updated'];
            $totalResponses += $levelReport['responses_created'] + $levelReport['responses_updated'];
            $totalPublishedSituations += $levelReport['situations_published'];
            $totalPassed += $levelReport['integrity_passed'];
            $totalFailed += $levelReport['integrity_failed'];

            $this->line(sprintf(
                'Level %d — %s | situations +%d/~%d | responses +%d/~%d | integrity ok=%d fail=%d | level_published=%s | situations_published=%d',
                $levelReport['order_index'],
                $levelReport['title'],
                $levelReport['situations_created'],
                $levelReport['situations_updated'],
                $levelReport['responses_created'],
                $levelReport['responses_updated'],
                $levelReport['integrity_passed'],
                $levelReport['integrity_failed'],
                $levelReport['level_published'] ? 'yes' : 'no',
                $levelReport['situations_published']
            ));

            foreach ($levelReport['withheld'] as $reason) {
                if ($reason) {
                    $this->warn('  withheld: ' . $reason);
                }
            }
        }

        // Accurate free count from DB after import
        $totalFree = NegotiationSituation::where('is_free', true)->count();
        $unpublishedCount = NegotiationSituation::where('is_published', false)->count();
        $publishedCount = NegotiationSituation::where('is_published', true)->count();

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Levels', (string) $totalLevels],
                ['Situations (created+updated this run)', (string) $totalSituations],
                ['Responses (created+updated this run)', (string) $totalResponses],
                ['Situations in DB', (string) NegotiationSituation::count()],
                ['Responses in DB', (string) NegotiationResponse::count()],
                ['Free situations', (string) $totalFree],
                ['Integrity passed', (string) $totalPassed],
                ['Integrity failed', (string) $totalFailed],
                ['Published situations', (string) $publishedCount],
                ['Unpublished situations', (string) $unpublishedCount],
            ]
        );
    }
}
