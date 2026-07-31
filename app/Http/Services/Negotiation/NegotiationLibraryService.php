<?php

namespace App\Http\Services\Negotiation;

use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationSituation;
use App\Models\Negotiation\UserNegotiationSituationNote;
use App\Services\MessageService;
use App\Services\NegotiationProgressService;

class NegotiationLibraryService
{
    public function __construct(
        protected NegotiationProgressService $progressService,
    ) {}

    /**
     * Published levels ordered by order_index, with batch progress for the user.
     *
     * @return array{levels: \Illuminate\Support\Collection, progress: array}
     */
    public function listLevels(int $userId): array
    {
        $levels = NegotiationLevel::query()
            ->where('is_published', true)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        $progress = $this->progressService->loadProgressDataForNegotiationLevels($levels, $userId);

        return [
            'levels' => $levels,
            'progress' => $progress,
        ];
    }

    /**
     * Published level intro. Locked levels still return meta (no situations).
     *
     * @return array{level: NegotiationLevel, progress: array}
     */
    public function showLevel(int $levelId, int $userId): array
    {
        $level = NegotiationLevel::query()
            ->where('id', $levelId)
            ->where('is_published', true)
            ->first();

        if (!$level) {
            MessageService::abort(404, 'messages.negotiation.level.not_found');
        }

        $progress = $this->progressService->loadProgressDataForNegotiationLevels(
            collect([$level]),
            $userId
        );

        return [
            'level' => $level,
            'progress' => $progress,
        ];
    }

    /**
     * Published situations for a level. Forbidden when the level itself is locked.
     *
     * @return array{situations: \Illuminate\Support\Collection, access: array, note_ids: array}
     */
    public function listSituationsForLevel(int $levelId, int $userId): array
    {
        $level = NegotiationLevel::query()
            ->where('id', $levelId)
            ->where('is_published', true)
            ->first();

        if (!$level) {
            MessageService::abort(404, 'messages.negotiation.level.not_found');
        }

        $accessStatus = $this->progressService->getNegotiationLevelAccessStatus($level, $userId);
        if ($accessStatus === 'locked') {
            MessageService::abort(403, 'messages.negotiation.level.locked', [], [
                'access_status' => 'locked',
            ]);
        }

        $situations = NegotiationSituation::query()
            ->where('negotiation_level_id', $level->id)
            ->where('is_published', true)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        $access = $this->progressService->loadAccessStatusesForSituations($situations, $userId);

        $noteIds = UserNegotiationSituationNote::query()
            ->where('user_id', $userId)
            ->whereIn('negotiation_situation_id', $situations->pluck('id')->all())
            ->whereNotNull('note_text')
            ->where('note_text', '!=', '')
            ->pluck('negotiation_situation_id')
            ->all();

        return [
            'situations' => $situations,
            'access' => $access,
            'note_ids' => array_flip($noteIds),
        ];
    }

    /**
     * Full study view for a published situation.
     *
     * @return array{situation: NegotiationSituation, access_status: string, note_text: ?string}
     */
    public function showSituation(int $situationId, int $userId): array
    {
        $situation = NegotiationSituation::query()
            ->where('id', $situationId)
            ->where('is_published', true)
            ->with('negotiationResponses')
            ->first();

        if (!$situation) {
            MessageService::abort(404, 'messages.negotiation.situation.not_found');
        }

        $accessStatus = $this->progressService->getSituationAccessStatus($situation, $userId);

        if (!$this->progressService->canAccessSituation($situation, $userId)) {
            $reason = $this->progressService->getSituationBlockingReason($situation, $userId);
            $messageKey = $reason === NegotiationProgressService::ACCESS_REASON_SUBSCRIPTION
                ? 'messages.negotiation.situation.subscription_required'
                : 'messages.negotiation.situation.locked';

            MessageService::abort(403, $messageKey, [], [
                'access_status' => $accessStatus,
                'access_reason' => $reason,
            ]);
        }

        $note = UserNegotiationSituationNote::query()
            ->where('user_id', $userId)
            ->where('negotiation_situation_id', $situation->id)
            ->first();

        return [
            'situation' => $situation,
            'access_status' => $accessStatus,
            'note_text' => $note?->note_text,
        ];
    }

    public function getNote(int $situationId, int $userId): array
    {
        $situation = $this->requireAccessiblePublishedSituation($situationId, $userId);

        $note = UserNegotiationSituationNote::query()
            ->where('user_id', $userId)
            ->where('negotiation_situation_id', $situation->id)
            ->first();

        return [
            'note_text' => $note?->note_text,
        ];
    }

    public function upsertNote(int $situationId, int $userId, ?string $noteText): array
    {
        $situation = $this->requireAccessiblePublishedSituation($situationId, $userId);

        $note = UserNegotiationSituationNote::withTrashed()->firstOrNew([
            'user_id' => $userId,
            'negotiation_situation_id' => $situation->id,
        ]);

        if ($note->trashed()) {
            $note->restore();
        }

        $note->note_text = $noteText;
        $note->save();

        return [
            'note_text' => $note->note_text,
        ];
    }

    private function requireAccessiblePublishedSituation(int $situationId, int $userId): NegotiationSituation
    {
        $situation = NegotiationSituation::query()
            ->where('id', $situationId)
            ->where('is_published', true)
            ->first();

        if (!$situation) {
            MessageService::abort(404, 'messages.negotiation.situation.not_found');
        }

        if (!$this->progressService->canAccessSituation($situation, $userId)) {
            $reason = $this->progressService->getSituationBlockingReason($situation, $userId);
            $accessStatus = $this->progressService->getSituationAccessStatus($situation, $userId);
            $messageKey = $reason === NegotiationProgressService::ACCESS_REASON_SUBSCRIPTION
                ? 'messages.negotiation.situation.subscription_required'
                : 'messages.negotiation.situation.locked';

            MessageService::abort(403, $messageKey, [], [
                'access_status' => $accessStatus,
                'access_reason' => $reason,
            ]);
        }

        return $situation;
    }
}
