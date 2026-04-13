<?php

namespace Tests\Feature;

use App\Http\Services\System\CertificateService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\TestCase;

class CertificateCourseDisabledTest extends TestCase
{
    public function test_check_eligibility_rejects_course_level_without_querying_course_completion(): void
    {
        $this->expectException(HttpResponseException::class);

        app(CertificateService::class)->checkCertificateEligibility(1, 1, null);
    }
}
