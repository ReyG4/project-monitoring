<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Services\BudgetCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectBudgetController extends Controller
{
    public function show(Project $project, BudgetCalculator $calculator): JsonResponse
    {
        Gate::authorize('view', $project);

        $roots = $project->tasks()
            ->whereNull('parent_task_id')
            ->with(['expenses', 'children.expenses', 'children.children'])
            ->orderBy('id')
            ->get();

        $project->setRelation('tasks', $roots);

        return response()->json([
            'data' => [
                'project' => $calculator->forProject($project)->toArray(),
                'activities' => $roots->map(fn (Task $root) => [
                    'id' => $root->id,
                    'name' => $root->name,
                    ...$calculator->forTask($root)->toArray(),
                    'subtasks' => $root->children->map(fn (Task $sub) => [
                        'id' => $sub->id,
                        'name' => $sub->name,
                        ...$calculator->forTask($sub)->toArray(),
                    ])->values(),
                ])->values(),
            ],
        ]);
    }
}