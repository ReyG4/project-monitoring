<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWeightsRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Services\WeightValidator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskWeightController extends Controller
{
    public function update(UpdateWeightsRequest $request, Project $project): AnonymousResourceCollection
    {
        $parentId = $request->validated('parent_task_id');
        $weights = collect($request->validated('weights'))->pluck('weight', 'id');

        if ($parentId !== null
            && ! $project->tasks()->whereNull('parent_task_id')->whereKey($parentId)->exists()) {
            throw ValidationException::withMessages([
                'parent_task_id' => 'Induk harus berupa aktivitas utama pada proyek ini.',
            ]);
        }

        $siblings = $project->tasks()->where('parent_task_id', $parentId)->get();
        $siblingIds = $siblings->pluck('id');

        if ($weights->keys()->diff($siblingIds)->isNotEmpty()
            || $siblingIds->diff($weights->keys())->isNotEmpty()) {
            throw ValidationException::withMessages([
                'weights' => 'Daftar bobot harus mencakup semua tugas pada level ini, tidak boleh kurang atau lebih.',
            ]);
        }

        $total = round((float) $weights->sum(), 2);

        if (abs($total - WeightValidator::TOTAL) >= WeightValidator::TOLERANCE) {
            throw ValidationException::withMessages([
                'weights' => "Total bobot harus 100%, saat ini {$total}%.",
            ]);
        }

        DB::transaction(function () use ($siblings, $weights) {
            foreach ($siblings as $task) {
                $task->update(['weight' => $weights[$task->id]]);
            }
        });

        $updated = $project->tasks()
            ->where('parent_task_id', $parentId)
            ->with('children.children')
            ->orderBy('id')
            ->get();

        return TaskResource::collection($updated)->additional([
            'meta' => ['weight_total' => $total],
        ]);
    }
}