<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subjectType = $this->subject_type ? class_basename($this->subject_type) : null;

        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'username' => $this->actor->username,
                'name' => $this->actor->name,
            ] : null,
            'subject' => [
                'type' => $subjectType ? strtolower($subjectType) : null,
                'id' => $this->subject_id,
            ],
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
