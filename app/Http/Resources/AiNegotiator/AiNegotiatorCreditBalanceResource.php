<?php

namespace App\Http\Resources\AiNegotiator;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiNegotiatorCreditBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{balance: int, allotment: int, consumed_this_cycle: int, cycle_ends_at: mixed, access_mode: string} $data */
        $data = $this->resource;

        return [
            'balance' => (int) $data['balance'],
            'allotment' => (int) $data['allotment'],
            'consumed_this_cycle' => (int) $data['consumed_this_cycle'],
            'cycle_ends_at' => $data['cycle_ends_at'],
            'access_mode' => $data['access_mode'],
        ];
    }
}
