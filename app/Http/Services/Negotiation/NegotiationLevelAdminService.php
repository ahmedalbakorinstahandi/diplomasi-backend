<?php

namespace App\Http\Services\Negotiation;

use App\Http\Permissions\Negotiation\NegotiationLevelPermission;
use App\Models\Negotiation\NegotiationLevel;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NegotiationLevelAdminService
{
    public function index(array $filters = []): LengthAwarePaginator
    {
        $query = NegotiationLevel::query()->withCount('negotiationSituations');

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['title'];
        $numericFields = ['order_index'];
        $dateFields = ['created_at'];
        $exactMatchFields = ['is_published'];
        $inFields = [];

        $query = NegotiationLevelPermission::filterIndex($query);

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

    public function show(int $id): NegotiationLevel
    {
        $level = NegotiationLevel::query()
            ->withCount('negotiationSituations')
            ->where('id', $id)
            ->first();

        if (!$level) {
            MessageService::abort(404, 'messages.negotiation_level.not_found');
        }

        return $level;
    }

    public function create(array $data): NegotiationLevel
    {
        // Mirror scenarios: always unpublished on create regardless of request payload.
        $payload = [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'how_to_study' => $data['how_to_study'] ?? null,
            'is_published' => false,
        ];

        $level = NegotiationLevel::create($payload);

        OrderHelper::assign($level, 'order_index');

        return $this->show($level->id);
    }

    public function update(array $data, NegotiationLevel $level): NegotiationLevel
    {
        $level->update($data);

        return $this->show($level->id);
    }

    public function delete(NegotiationLevel $level): void
    {
        // INTENTIONAL DIFFERENCE FROM COURSE LevelService::delete:
        // Course levels cascade-wipe learner progress/attempts. Negotiation levels must
        // PRESERVE every user_negotiation_* progress/attempt row so acquired learner
        // progress is never revoked. Soft-delete the level (and its situations/responses
        // for content consistency) only — do NOT touch any user_negotiation_* tables.
        $level->loadMissing('negotiationSituations.negotiationResponses');

        foreach ($level->negotiationSituations as $situation) {
            $situation->negotiationResponses()->delete();
            $situation->delete();
        }

        $level->delete();
    }

    public function reorder(NegotiationLevel $level, array $validatedData): NegotiationLevel
    {
        OrderHelper::reorder($level, (int) $validatedData['new_order_index'], 'order_index');

        return $this->show($level->id);
    }
}
