<?php

namespace App\Console\Commands\AiNegotiator;

use App\Models\System\Setting;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderException;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Llm\Contracts\LlmResponse;
use App\Services\AiNegotiator\Prompts\PromptTemplateService;
use Illuminate\Console\Command;
use Throwable;

class TestClaudeConnectionCommand extends Command
{
    /**
     * Approximate Claude Sonnet pricing — update when Anthropic pricing changes.
     * Rates are USD per million tokens.
     */
    private const INPUT_USD_PER_MTOK = 3.00;

    private const OUTPUT_USD_PER_MTOK = 15.00;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-negotiator:test-claude
                            {--test=all : all|auth|arabic|json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Live diagnostic test for Claude API connection. Costs a few cents in API credits.';

    /**
     * @var list<array{test: string, result: string, in: int, out: int, cost: string}>
     */
    private array $results = [];

    private int $totalInputTokens = 0;

    private int $totalOutputTokens = 0;

    public function handle(LlmProviderInterface $llm, PromptTemplateService $prompts): int
    {
        $apiKey = (string) config('services.ai_negotiator.claude.api_key', '');
        if (trim($apiKey) === '') {
            $this->error('Missing AI_NEGOTIATOR_CLAUDE_API_KEY in .env. Add your key and run: php artisan config:clear');

            return self::FAILURE;
        }

        $selected = strtolower(trim((string) $this->option('test')));
        $allowed = ['all', 'auth', 'arabic', 'json'];
        if (!in_array($selected, $allowed, true)) {
            $this->error("Invalid --test={$selected}. Allowed: " . implode('|', $allowed));

            return self::FAILURE;
        }

        $model = $this->resolveModel();
        $this->info("Provider: {$llm->name()} | Model: {$model}");
        $this->newLine();

        $tests = match ($selected) {
            'auth' => ['auth'],
            'arabic' => ['arabic'],
            'json' => ['json'],
            default => ['auth', 'arabic', 'json'],
        };

        foreach ($tests as $test) {
            match ($test) {
                'auth' => $this->runAuthTest($llm, $model),
                'arabic' => $this->runArabicTest($llm, $prompts, $model),
                'json' => $this->runJsonTest($llm, $model),
            };
            $this->newLine();
        }

        $this->printSummary();

        $failed = collect($this->results)->contains(fn (array $row) => str_contains($row['result'], 'FAIL'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function runAuthTest(LlmProviderInterface $llm, string $model): void
    {
        $this->line('=== Test 1: Auth & Basic Connectivity ===');

        $systemPrompt = 'You are a helpful assistant. Respond in exactly one short sentence.';
        $messages = [['role' => 'user', 'content' => 'Say hello.']];
        $options = ['model' => $model, 'max_tokens' => 50];

        $this->printRequest($systemPrompt, $messages, $options);

        try {
            $response = $llm->chat($systemPrompt, $messages, $options);
            $this->printResponse($response);

            $pass = $response->content !== ''
                && $response->inputTokens > 0
                && $response->outputTokens > 0
                && $response->model !== '';

            if ($pass) {
                $this->info('✅ PASS: Auth and basic connectivity OK.');
                $this->recordResult('Auth', '✅ PASS', $response);
            } else {
                $this->error('❌ FAIL: Response missing content, tokens, or model.');
                $this->recordResult('Auth', '❌ FAIL', $response);
            }
        } catch (LlmProviderException $e) {
            $this->failWithException('Auth', $e);
        } catch (Throwable $e) {
            $this->failWithThrowable('Auth', $e);
        }
    }

    private function runArabicTest(
        LlmProviderInterface $llm,
        PromptTemplateService $prompts,
        string $model
    ): void {
        $this->line('=== Test 2: Arabic Round-Trip ===');

        $systemPrompt = $prompts->buildIntakePrompt();
        $messages = [['role' => 'user', 'content' => 'مرحباً، أريد التدرب على تفاوض راتب.']];
        $options = ['model' => $model, 'max_tokens' => 500];

        $this->printRequest($systemPrompt, $messages, $options);

        try {
            $response = $llm->chat($systemPrompt, $messages, $options);
            $this->printResponse($response);
            $this->line('--- Full response content ---');
            $this->line($response->content);
            $this->line('--- end ---');

            $hasArabic = (bool) preg_match('/\p{Arabic}/u', $response->content);
            $longEnough = mb_strlen($response->content) > 20;

            if ($hasArabic && $longEnough) {
                $this->info('✅ PASS: Arabic response received and is long enough.');
                $this->recordResult('Arabic', '✅ PASS', $response);
            } else {
                $reasons = [];
                if (!$hasArabic) {
                    $reasons[] = 'no Arabic characters';
                }
                if (!$longEnough) {
                    $reasons[] = 'content length <= 20';
                }
                $this->error('❌ FAIL: ' . implode('; ', $reasons));
                $this->recordResult('Arabic', '❌ FAIL', $response);
            }
        } catch (LlmProviderException $e) {
            $this->failWithException('Arabic', $e);
        } catch (Throwable $e) {
            $this->failWithThrowable('Arabic', $e);
        }
    }

    private function runJsonTest(LlmProviderInterface $llm, string $model): void
    {
        $this->line('=== Test 3: Structured JSON Output ===');

        $systemPrompt = <<<'PROMPT'
أنت محلل بيانات. أعد فقط JSON صالحاً بدون أي نص خارج JSON.
الشكل المطلوب:
{
  "score": <رقم بين 0 و 100>,
  "grade": "excellent|good|poor",
  "notes": "<جملة واحدة>"
}
PROMPT;

        $messages = [['role' => 'user', 'content' => 'قيّم هذا: طالب حصل على 85 من 100 في الامتحان.']];
        $options = ['model' => $model, 'max_tokens' => 200];

        $this->printRequest($systemPrompt, $messages, $options);

        try {
            $response = $llm->chat($systemPrompt, $messages, $options);
            $this->printResponse($response);

            $parsed = $this->parseJsonContent($response->content);
            if ($parsed === null) {
                $this->error('❌ FAIL: Response is not valid JSON.');
                $this->line('Raw content:');
                $this->line($response->content);
                $this->recordResult('JSON', '❌ FAIL', $response);

                return;
            }

            $score = $parsed['score'] ?? null;
            $grade = $parsed['grade'] ?? null;
            $notes = $parsed['notes'] ?? null;
            $gradeOk = is_string($grade) && in_array($grade, ['excellent', 'good', 'poor'], true);
            $scoreOk = is_numeric($score) && (float) $score >= 0 && (float) $score <= 100;
            $notesOk = is_string($notes) && $notes !== '';

            if ($scoreOk && $gradeOk && $notesOk) {
                $this->info('✅ PASS: Structured JSON is valid and matches schema.');
                $this->line('Parsed: ' . json_encode($parsed, JSON_UNESCAPED_UNICODE));
                $this->recordResult('JSON', '✅ PASS', $response);
            } else {
                $this->error('❌ FAIL: JSON parsed but schema validation failed.');
                $this->line('Parsed: ' . json_encode($parsed, JSON_UNESCAPED_UNICODE));
                $this->recordResult('JSON', '❌ FAIL', $response);
            }
        } catch (LlmProviderException $e) {
            $this->failWithException('JSON', $e);
        } catch (Throwable $e) {
            $this->failWithThrowable('JSON', $e);
        }
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    private function printRequest(string $systemPrompt, array $messages, array $options): void
    {
        $truncated = mb_substr($systemPrompt, 0, 200);
        if (mb_strlen($systemPrompt) > 200) {
            $truncated .= '…';
        }

        $this->line('Request:');
        $this->line('  system: ' . $truncated);
        $this->line('  messages: ' . json_encode($messages, JSON_UNESCAPED_UNICODE));
        $this->line('  options: ' . json_encode($options, JSON_UNESCAPED_UNICODE));
    }

    private function printResponse(LlmResponse $response): void
    {
        $preview = mb_substr($response->content, 0, 300);
        if (mb_strlen($response->content) > 300) {
            $preview .= '…';
        }

        $cost = $this->estimateCost($response->inputTokens, $response->outputTokens);

        $this->line('Response:');
        $this->line('  content: ' . $preview);
        $this->line("  tokens: in={$response->inputTokens} out={$response->outputTokens}");
        $this->line("  model: {$response->model}");
        $this->line("  stop_reason: {$response->stopReason}");
        $this->line("  cost estimate: {$cost}");
    }

    private function recordResult(string $test, string $result, ?LlmResponse $response): void
    {
        $in = $response?->inputTokens ?? 0;
        $out = $response?->outputTokens ?? 0;
        $this->totalInputTokens += $in;
        $this->totalOutputTokens += $out;

        $this->results[] = [
            'test' => $test,
            'result' => $result,
            'in' => $in,
            'out' => $out,
            'cost' => $this->estimateCost($in, $out),
        ];
    }

    private function failWithException(string $test, LlmProviderException $e): void
    {
        $this->error('❌ FAIL: ' . $e::class . ': ' . $e->getMessage());
        $this->recordResult($test, '❌ FAIL', null);
    }

    private function failWithThrowable(string $test, Throwable $e): void
    {
        $this->error('❌ FAIL: ' . $e::class . ': ' . $e->getMessage());
        $trace = explode("\n", $e->getTraceAsString());
        foreach (array_slice($trace, 0, 5) as $line) {
            $this->line('  ' . $line);
        }
        $this->recordResult($test, '❌ FAIL', null);
    }

    private function printSummary(): void
    {
        $rows = [];
        foreach ($this->results as $row) {
            $rows[] = [
                $row['test'],
                $row['result'],
                (string) $row['in'],
                (string) $row['out'],
                $row['cost'],
            ];
        }

        $rows[] = [
            'TOTAL',
            '',
            (string) $this->totalInputTokens,
            (string) $this->totalOutputTokens,
            $this->estimateCost($this->totalInputTokens, $this->totalOutputTokens),
        ];

        $this->table(
            ['Test', 'Result', 'In Tokens', 'Out Tokens', 'Cost'],
            $rows
        );
    }

    private function estimateCost(int $inputTokens, int $outputTokens): string
    {
        $usd = ($inputTokens / 1_000_000) * self::INPUT_USD_PER_MTOK
            + ($outputTokens / 1_000_000) * self::OUTPUT_USD_PER_MTOK;

        return '~$' . number_format($usd, 4) . ' USD';
    }

    private function resolveModel(): string
    {
        try {
            $setting = Setting::query()->where('key_name', 'ai_negotiator.llm_model')->first();
            $value = $setting?->value;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        } catch (Throwable) {
            // fall through
        }

        return 'claude-sonnet-4-6';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonContent(string $content): ?array
    {
        $trimmed = trim($content);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->warn('JSON error: ' . json_last_error_msg());

            return null;
        }

        return $decoded;
    }
}
