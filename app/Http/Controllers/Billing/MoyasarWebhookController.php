<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Services\Billing\MoyasarWebhookService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class MoyasarWebhookController extends Controller
{
    public function __construct(
        protected MoyasarWebhookService $moyasarWebhookService
    ) {}

    public function receive(Request $request)
    {
        $result = $this->moyasarWebhookService->ingest($request->all());

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => [
                'accepted' => true,
                'duplicate' => $result['duplicate'],
                'webhook_event_id' => $result['event']->id,
                'payload_id' => $result['event']->payload_id,
            ],
        ]);
    }
}
