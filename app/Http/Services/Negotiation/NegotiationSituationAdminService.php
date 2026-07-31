<?php

namespace App\Http\Services\Negotiation;

use App\Http\Permissions\Negotiation\NegotiationSituationPermission;
use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NegotiationSituationAdminService
{
    /** @var list<string> */
    public const REQUIRED_STYLES = ['gentle', 'diplomatic', 'firm'];

    public function index(array $filters = []): LengthAwarePaginator
    {
        $query = NegotiationSituation::query()->with(['negotiationResponses']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['prompt_text'];
        $numericFields = ['order_index'];
        $dateFields = ['created_at'];
        $exactMatchFields = ['is_published', 'is_free', 'negotiation_level_id', 'prompt_type'];
        $inFields = [];

        $query = NegotiationSituationPermission::filterIndex($query);

        return FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );
    }

    public function show(int $id): NegotiationSituation
    {
        $situation = NegotiationSituation::query()
            ->with(['negotiationResponses'])
            ->where('id', $id)
            ->first();

        if (!$situation) {
            MessageService::abort(404, 'messages.negotiation_situation.not_found');
        }

        return $situation;
    }

    public function create(array $data): NegotiationSituation
    {
        return DB::transaction(function () use ($data) {
            // Mirror scenarios: always unpublished on create regardless of request payload.
            $situation = NegotiationSituation::create([
                'negotiation_level_id' => $data['negotiation_level_id'],
                'prompt_text' => $data['prompt_text'],
                'prompt_context' => $data['prompt_context'] ?? null,
                'prompt_type' => $data['prompt_type'],
                'insight' => $data['insight'] ?? null,
                'is_free' => (bool) $data['is_free'],
                'is_published' => false,
            ]);

            // Per-level order_index: OrderHelper with scope on negotiation_level_id
            // (global OrderHelper alone would renumber across levels).
            OrderHelper::assign(
                $situation,
                'order_index',
                fn ($query) => $query->where('negotiation_level_id', $situation->negotiation_level_id)
            );

            $this->syncResponses($situation, $data['responses']);

            return $this->show($situation->id);
        });
    }

    public function update(array $data, NegotiationSituation $situation): NegotiationSituation
    {
        return DB::transaction(function () use ($data, $situation) {
            if (array_key_exists('is_published', $data) && $data['is_published'] === true) {
                $this->assertPublishable($situation, $data['responses'] ?? null);
            }

            $situationFields = collect($data)->except(['responses'])->all();
            if (!empty($situationFields)) {
                $situation->update($situationFields);
            }

            if (array_key_exists('responses', $data)) {
                $this->syncResponses($situation, $data['responses']);
            }

            return $this->show($situation->id);
        });
    }

    public function delete(NegotiationSituation $situation): void
    {
        // INTENTIONAL DIFFERENCE FROM COURSE ScenarioService::delete:
        // Course scenarios cascade-wipe learner attempts. Negotiation situations must
        // PRESERVE every user_negotiation_* progress/attempt/answer/note row so acquired
        // learner progress is never revoked. Soft-delete the situation and its responses
        // only — do NOT touch any user_negotiation_* tables.
        $situation->negotiationResponses()->delete();
        $situation->delete();
    }

    public function reorder(NegotiationSituation $situation, array $validatedData): NegotiationSituation
    {
        OrderHelper::reorder(
            $situation,
            (int) $validatedData['new_order_index'],
            'order_index',
            fn ($query) => $query->where('negotiation_level_id', $situation->negotiation_level_id)
        );

        return $this->show($situation->id);
    }

    /**
     * Upsert exactly one response per style. Never creates a duplicate style for the situation.
     *
     * @param  list<array{style: string, response_text: string, explanation: string}>  $responses
     */
    private function syncResponses(NegotiationSituation $situation, array $responses): void
    {
        foreach ($responses as $row) {
            $existing = NegotiationResponse::withTrashed()
                ->where('negotiation_situation_id', $situation->id)
                ->where('style', $row['style'])
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update([
                    'response_text' => $row['response_text'],
                    'explanation' => $row['explanation'],
                ]);

                continue;
            }

            NegotiationResponse::create([
                'negotiation_situation_id' => $situation->id,
                'style' => $row['style'],
                'response_text' => $row['response_text'],
                'explanation' => $row['explanation'],
            ]);
        }
    }

    /**
     * Publish requires all 3 styles present with non-empty response_text and explanation.
     * Uses the incoming responses payload when provided; otherwise the persisted rows.
     *
     * @param  list<array{style: string, response_text: string, explanation: string}>|null  $incomingResponses
     */
    private function assertPublishable(NegotiationSituation $situation, ?array $incomingResponses): void
    {
        $byStyle = [];

        if (is_array($incomingResponses)) {
            foreach ($incomingResponses as $row) {
                $byStyle[$row['style']] = $row;
            }
        } else {
            $situation->loadMissing('negotiationResponses');
            foreach ($situation->negotiationResponses as $response) {
                $byStyle[$response->style] = [
                    'style' => $response->style,
                    'response_text' => $response->response_text,
                    'explanation' => $response->explanation,
                ];
            }
        }

        foreach (self::REQUIRED_STYLES as $style) {
            $row = $byStyle[$style] ?? null;
            if (
                !$row
                || trim((string) ($row['response_text'] ?? '')) === ''
                || trim((string) ($row['explanation'] ?? '')) === ''
            ) {
                MessageService::abort(422, 'messages.negotiation_situation.cannot_publish_incomplete');
            }
        }
    }
}
