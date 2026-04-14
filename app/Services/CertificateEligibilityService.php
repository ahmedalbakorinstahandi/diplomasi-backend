<?php

namespace App\Services;

use App\Models\Learning\Level;
use App\Models\Learning\Lesson;
use App\Models\Learning\LevelTrack;
use App\Models\Scenarios\Scenario;
use App\Models\System\Certificate;
use App\Models\System\UserLevelCertificateEligibility;
use App\Models\Users\User;

class CertificateEligibilityService
{
    public function evaluateLevelForUser(int $userId, Level $level): UserLevelCertificateEligibility
    {
        $user = User::find($userId);

        $eligibility = UserLevelCertificateEligibility::firstOrNew([
            'user_id' => $userId,
            'level_id' => $level->id,
        ]);

        $eligibility->course_id = (int) $level->course_id;
        $eligibility->last_evaluated_at = now();

        if (!$level->has_certificate) {
            $eligibility->is_eligible = false;
            $eligibility->artifact_status = 'not_generated';
            $eligibility->regeneration_reason = null;
            $eligibility->save();
            return $eligibility;
        }

        $tracks = LevelTrack::where('level_id', $level->id)
            ->with('trackable')
            ->orderBy('order_index')
            ->get();

        $requirementsComplete = true;
        foreach ($tracks as $track) {
            if (!$track->trackable) {
                continue;
            }
            if (!$this->isTrackablePublished($track->trackable)) {
                continue;
            }
            if (!$this->isTrackCompleted($track, $userId)) {
                $requirementsComplete = false;
                break;
            }
        }

        $alreadyEligible = (bool) $eligibility->is_eligible;
        $meetsSubscriptionGate = (bool) $level->is_free || ($user ? $user->hasActiveSubscription() : false);
        $shouldBecomeEligible = $requirementsComplete && $meetsSubscriptionGate;

        if (!$alreadyEligible && $shouldBecomeEligible) {
            $eligibility->is_eligible = true;
            $eligibility->first_eligible_at = $eligibility->first_eligible_at ?? now();
            $eligibility->last_eligible_at = now();
        }

        if (!$eligibility->is_eligible) {
            $eligibility->artifact_status = 'not_generated';
            $eligibility->regeneration_reason = null;
            $eligibility->generated_certificate_id = null;
            $eligibility->save();
            return $eligibility;
        }

        $certificate = Certificate::where('user_id', $userId)
            ->where('course_id', $level->course_id)
            ->where('level_id', $level->id)
            ->whereNotNull('issued_at')
            ->latest('id')
            ->first();

        if (!$certificate && $eligibility->generated_certificate_id) {
            $eligibility->artifact_status = 'regeneration_needed';
            $eligibility->regeneration_reason = 'certificate_deleted';
            $eligibility->generated_certificate_id = null;
            $eligibility->save();
            return $eligibility;
        }

        if (!$certificate) {
            if ($eligibility->artifact_status !== 'regeneration_needed') {
                $eligibility->artifact_status = 'not_generated';
                $eligibility->regeneration_reason = null;
            }
            $eligibility->save();
            return $eligibility;
        }

        $eligibility->generated_certificate_id = $certificate->id;

        if ($this->needsTemplateRefresh($certificate, $level)) {
            $eligibility->artifact_status = 'regeneration_needed';
            $eligibility->regeneration_reason = 'template_changed';
            $eligibility->save();
            return $eligibility;
        }

        if (!$certificate->image_url || !file_exists(storage_path('app/public/' . $certificate->image_url))) {
            $eligibility->artifact_status = 'regeneration_needed';
            $eligibility->regeneration_reason = 'artifact_missing';
            $eligibility->save();
            return $eligibility;
        }

        $eligibility->artifact_status = 'generated';
        $eligibility->regeneration_reason = null;
        $eligibility->save();

        return $eligibility;
    }

    public function markGenerationFailed(int $userId, int $levelId): void
    {
        $eligibility = UserLevelCertificateEligibility::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->first();
        if (!$eligibility || !$eligibility->is_eligible) {
            return;
        }
        $eligibility->artifact_status = 'regeneration_needed';
        $eligibility->regeneration_reason = 'generation_failed';
        $eligibility->save();
    }

    private function needsTemplateRefresh(Certificate $certificate, Level $level): bool
    {
        $snapshotPath = (string) ($certificate->template_snapshot_path ?? '');
        $levelTemplatePath = (string) ($level->certificate_template_path ?? '');
        $snapshotConfig = (array) ($certificate->template_snapshot_config ?? []);
        $levelConfig = (array) ($level->certificate_template_config ?? []);

        if ($snapshotPath === '' || $levelTemplatePath === '') {
            return false;
        }

        if (basename($snapshotPath) !== basename($levelTemplatePath)) {
            return true;
        }

        return md5(json_encode($snapshotConfig)) !== md5(json_encode($levelConfig));
    }

    private function isTrackablePublished($trackable): bool
    {
        if ($trackable instanceof Lesson || $trackable instanceof Scenario) {
            return (bool) ($trackable->is_published ?? false);
        }
        return false;
    }

    private function isTrackCompleted(LevelTrack $track, int $userId): bool
    {
        $progressService = app(TrackProgressService::class);
        return $track->trackable ? $progressService->isTrackCompleted($track->trackable, $userId) : false;
    }
}
