<?php

namespace App\Http\Resources;

use App\Services\ProgressCalculator;
use App\Services\WeightValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $isLeaf = $this->children->isEmpty();

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'parent_task_id' => $this->parent_task_id,
            'name' => $this->name,
            'pic_name' => $this->pic_name,
            'weight' => (float) $this->weight,
            'progress' => app(ProgressCalculator::class)->forTask($this->resource),
            'is_leaf' => $isLeaf,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'planned_start' => $this->planned_start?->toDateString(),
            'target_end' => $this->target_end?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'budget_plan' => (float) $this->budget_plan,
            'notes' => $this->notes,
            'children_weight_total' => $isLeaf ? null : app(WeightValidator::class)->total($this->children),
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}