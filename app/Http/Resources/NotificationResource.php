<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? class_basename($this->type),
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'actor' => $data['actor'] ?? null,
            'entity' => $data['entity'] ?? null,
            'context' => $data['context'] ?? [],
            'read_at' => $this->read_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
