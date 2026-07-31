<?php

namespace Tests\Unit\Negotiation;

use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use App\Services\NegotiationQuizService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class NegotiationQuizServiceTest extends TestCase
{
    private NegotiationQuizService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->app['db']->purge('sqlite');
        $this->app['db']->reconnect('sqlite');

        $this->createSchema();
        $this->service = new NegotiationQuizService();
    }

    public function test_quick_test_yields_three_questions_one_per_style(): void
    {
        $situation = $this->makeSituationWithResponses();

        $result = $this->service->buildQuickTest($situation, seed: 42);

        $this->assertSame(42, $result['seed']);
        $this->assertCount(3, $result['questions']);
        $this->assertSame(
            NegotiationQuizService::STYLES,
            array_column($result['questions'], 'asked_style')
        );

        foreach ($result['questions'] as $question) {
            $this->assertSame($situation->id, $question['situation_id']);
            $this->assertCount(3, $question['options']);
            $this->assertArrayHasKey('correct_response_id', $question);
            foreach ($question['options'] as $option) {
                $this->assertArrayHasKey('id', $option);
                $this->assertArrayHasKey('response_text', $option);
                $this->assertArrayNotHasKey('style', $option);
            }
        }
    }

    public function test_correct_option_maps_to_asked_style(): void
    {
        $situation = $this->makeSituationWithResponses();
        $byStyle = $situation->negotiationResponses->keyBy('style');

        $result = $this->service->buildQuickTest($situation, seed: 7);

        foreach ($result['questions'] as $question) {
            $expectedId = (int) $byStyle[$question['asked_style']]->id;
            $this->assertSame($expectedId, $question['correct_response_id']);
            $optionIds = array_column($question['options'], 'id');
            $this->assertContains($expectedId, $optionIds);
        }
    }

    public function test_option_shuffle_is_deterministic_per_seed(): void
    {
        $situation = $this->makeSituationWithResponses();

        $a = $this->service->buildQuickTest($situation, seed: 100);
        $b = $this->service->buildQuickTest($situation, seed: 100);
        $c = $this->service->buildQuickTest($situation, seed: 200);

        $this->assertSame(
            array_column($a['questions'][0]['options'], 'id'),
            array_column($b['questions'][0]['options'], 'id')
        );

        $ordersDiffer = false;
        for ($i = 0; $i < 3; $i++) {
            if (
                array_column($a['questions'][$i]['options'], 'id')
                !== array_column($c['questions'][$i]['options'], 'id')
            ) {
                $ordersDiffer = true;
                break;
            }
        }
        $this->assertTrue($ordersDiffer, 'Different seeds should produce different option orders');
    }

    public function test_final_test_yields_fifteen_distinct_pairs_from_full_bank(): void
    {
        $level = NegotiationLevel::create([
            'title' => 'Level 1',
            'order_index' => 1,
            'is_published' => true,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            $this->makeSituationWithResponses($level->id, $i);
        }

        $result = $this->service->buildFinalTest($level->fresh(), seed: 55);

        $this->assertCount(15, $result['questions']);

        $pairs = [];
        foreach ($result['questions'] as $question) {
            $key = $question['situation_id'] . ':' . $question['asked_style'];
            $this->assertArrayNotHasKey($key, $pairs, 'Pairs must be distinct within one final test');
            $pairs[$key] = true;
            $this->assertContains($question['asked_style'], NegotiationQuizService::STYLES);
            $this->assertCount(3, $question['options']);
        }
    }

    public function test_final_test_throws_when_bank_too_small(): void
    {
        $level = NegotiationLevel::create([
            'title' => 'Thin level',
            'order_index' => 1,
            'is_published' => true,
        ]);
        $this->makeSituationWithResponses($level->id, 1); // bank size = 3

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires at least 15');

        $this->service->buildFinalTest($level->fresh(), seed: 1);
    }

    public function test_quick_test_throws_when_responses_incomplete(): void
    {
        $level = NegotiationLevel::create([
            'title' => 'L',
            'order_index' => 1,
            'is_published' => true,
        ]);
        $situation = NegotiationSituation::create([
            'negotiation_level_id' => $level->id,
            'prompt_text' => 'Incomplete',
            'prompt_type' => 'quote',
            'order_index' => 1,
            'is_published' => true,
            'is_free' => true,
        ]);
        NegotiationResponse::create([
            'negotiation_situation_id' => $situation->id,
            'style' => 'gentle',
            'response_text' => 'Only gentle',
            'explanation' => 'Why',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("missing a 'diplomatic' response");

        $this->service->buildQuickTest($situation->fresh(), seed: 1);
    }

    public function test_grade_answer_correctness_and_null_handling(): void
    {
        $situation = $this->makeSituationWithResponses();
        $byStyle = $situation->negotiationResponses->keyBy('style');

        $correct = $this->service->gradeAnswer(
            $situation,
            'firm',
            (int) $byStyle['firm']->id
        );
        $this->assertTrue($correct['is_correct']);
        $this->assertSame((int) $byStyle['firm']->id, $correct['correct_response_id']);
        $this->assertSame($byStyle['firm']->explanation, $correct['feedback']);

        $wrong = $this->service->gradeAnswer(
            $situation,
            'firm',
            (int) $byStyle['gentle']->id
        );
        $this->assertFalse($wrong['is_correct']);
        $this->assertSame((int) $byStyle['firm']->id, $wrong['correct_response_id']);

        $nullPick = $this->service->gradeAnswer($situation, 'diplomatic', null);
        $this->assertFalse($nullPick['is_correct']);

        $invalidId = $this->service->gradeAnswer($situation, 'diplomatic', 999999);
        $this->assertFalse($invalidId['is_correct']);
    }

    public function test_score_attempt_math(): void
    {
        $scored = $this->service->scoreAttempt([
            ['is_correct' => true],
            ['is_correct' => false],
            ['is_correct' => true],
        ]);

        $this->assertSame(2, $scored['correct_count']);
        $this->assertSame(3, $scored['total']);
        $this->assertSame(66.67, $scored['score']);

        $empty = $this->service->scoreAttempt([]);
        $this->assertSame(0, $empty['correct_count']);
        $this->assertSame(0, $empty['total']);
        $this->assertSame(0.0, $empty['score']);

        $perfect = $this->service->scoreAttempt([
            ['is_correct' => true],
            ['is_correct' => true],
        ]);
        $this->assertSame(100.0, $perfect['score']);
    }

    private function createSchema(): void
    {
        Schema::create('negotiation_levels', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('how_to_study')->nullable();
            $table->unsignedBigInteger('order_index')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('negotiation_situations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('negotiation_level_id');
            $table->text('prompt_text');
            $table->string('prompt_type')->default('quote');
            $table->unsignedBigInteger('order_index')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_free');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('negotiation_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->string('style');
            $table->text('response_text');
            $table->text('explanation');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function makeSituationWithResponses(?int $levelId = null, int $orderIndex = 1): NegotiationSituation
    {
        if ($levelId === null) {
            $level = NegotiationLevel::create([
                'title' => 'Level',
                'order_index' => 1,
                'is_published' => true,
            ]);
            $levelId = $level->id;
        }

        $situation = NegotiationSituation::create([
            'negotiation_level_id' => $levelId,
            'prompt_text' => 'Prompt ' . $orderIndex,
            'prompt_type' => 'quote',
            'order_index' => $orderIndex,
            'is_published' => true,
            'is_free' => true,
        ]);

        foreach (NegotiationQuizService::STYLES as $style) {
            NegotiationResponse::create([
                'negotiation_situation_id' => $situation->id,
                'style' => $style,
                'response_text' => "Response {$style} for {$orderIndex}",
                'explanation' => "Why {$style} for {$orderIndex}",
            ]);
        }

        return $situation->fresh(['negotiationResponses']);
    }
}
