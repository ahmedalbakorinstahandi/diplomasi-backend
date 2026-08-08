<?php

namespace App\Http\Controllers\AiNegotiator;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiNegotiator\AiNegotiatorCreditBalanceResource;
use App\Http\Services\AiNegotiator\Credits\CreditService;
use App\Models\Users\User;
use App\Services\MessageService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class AiNegotiatorCreditController extends Controller
{
    public function __construct(
        protected CreditService $creditService,
    ) {}

    public function balance(Request $request)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $balance = $this->creditService->getCurrentBalance($user);

        return ResponseService::response([
            'success' => true,
            'data' => new AiNegotiatorCreditBalanceResource($balance),
            'status' => 200,
        ]);
    }
}
