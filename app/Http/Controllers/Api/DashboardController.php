<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Services\ProgressCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProgressCalculator $calculator): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $projects = Project::visibleTo($request->user())
            ->with('tasks.children.children')
            ->orderBy('name')
            ->get();

        $items = $projects->map(fn (Project $project) => [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'progress' => $calculator->forProject($project),
        ]);

        $overdue = $projects
            ->flatMap(fn (Project $project) => $project->tasks
                ->filter(fn (Task $task) => $task->isOverdue())
                ->map(fn (Task $task) => [
                    'id' => $task->id,
                    'name' => $task->name,
                    'pic_name' => $task->pic_name,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'target_end' => $task->target_end->toDateString(),
                    'days_late' => abs((int) round($task->target_end->diffInDays(today()))),
                ]))
            ->sortByDesc('days_late')
            ->values();

        return response()->json([
            'data' => [
                'total' => $items->count(),
                'by_status' => $items->countBy('status'),
                'average_progress' => round((float) $items->avg('progress'), 2),
                'waiting_review' => $items->where('status', 'diajukan')->count(),
                'overdue_count' => $overdue->count(),
                'projects' => $items->values(),
                'overdue' => $overdue->take(10)->values(),
            ],
        ]);
    }
}