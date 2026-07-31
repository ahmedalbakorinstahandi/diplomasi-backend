<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NegotiationSituationListItemResource extends JsonResource
{
    protected static ?array $accessCache = null;

    protected static ?array $noteIdsCache = null;

    public static function setAccessCache(array $access): void
    {
        self::$accessCache = $access;
    }

    public static function setNoteIdsCache(array $noteIds): void
    {
        self::$noteIdsCache = $noteIds;
    }

    public static function clearCaches(): void
    {
        self::$accessCache = null;
        self::$noteIdsCache = null;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_index' => $this->order_index,
            'is_free' => (bool) $this->is_free,
            'access_status' => self::$accessCache[$this->id] ?? 'locked',
            'has_note' => isset(self::$noteIdsCache[$this->id]),
        ];
    }
}
