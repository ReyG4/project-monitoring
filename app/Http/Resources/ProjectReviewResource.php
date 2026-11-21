<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'decision' => $this->decision->value,
            'decision_label' => $this->decision->label(),
            'notes' => $this->notes,
            'reviewer' => $this->reviewer?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}