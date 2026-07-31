<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NegotiationLevelResource extends JsonResource
{
    protected static ?array $progressCache = null;

    protected bool $detail = false;

    public static function setProgressDataCache(array $progressData): void
    {
        self::$progressCache = $progressData;
    }

    public static function clearProgressDataCache(): void
    {
        self::$progressCache = null;
    }

    public function asDetail(): self
    {
        $this->detail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $progress = self::$progressCache[$this->id] ?? [
            'access_status' => 'locked',
            'completed_situations' => 0,
            'total_situations' => 0,
        ];

        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'order_index' => $this->order_index,
            'situations_count' => (int) ($progress['total_situations'] ?? 0),
            'access_status' => $progress['access_status'] ?? 'locked',
            'progress' => [
                'completed_situations' => (int) ($progress['completed_situations'] ?? 0),
                'total_situations' => (int) ($progress['total_situations'] ?? 0),
            ],
        ];

        if ($this->detail) {
            $data['description'] = $this->description;
            $data['how_to_study'] = $this->how_to_study;
        }

        return $data;
    }
}
