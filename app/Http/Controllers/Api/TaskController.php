<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Task;
use App\Services\ProgressCalculator;
use App\Services\WeightValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        $roots = $project->tasks()
            ->whereNull('parent_task_id')
            ->with('children.children')
            ->orderBy('id')
            ->get();

        $project->setRelation('tasks', $roots);

        return TaskResource::collection($roots)->additional([
            'meta' => [
                'project_progress' => app(ProgressCalculator::class)->forProject($project),
                'weight_total' => app(WeightValidator::class)->total($roots),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $parentId = $request->validated('parent_task_id');

        if ($parentId !== null && Task::findOrFail($parentId)->expenses()->exists()) {
            throw ValidationException::withMessages([
                'parent_task_id' => 'Aktivitas ini sudah punya catatan biaya, tidak bisa diberi subaktivitas.',
            ]);
        }

        $task = new Task(Arr::except($request->validated(), ['parent_task_id']));
        $task->project_id = $project->id;
        $task->parent_task_id = $parentId;
        $task->save();

        return (new TaskResource($task->refresh()->loadMissing('children.children')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($task->loadMissing('children.children'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        if ($request->has('progress') && $task->children()->exists()) {
            throw ValidationException::withMessages([
                'progress' => 'Progres aktivitas yang punya subaktivitas dihitung otomatis dan tidak bisa diisi manual.',
            ]);
        }

        $task->update($request->validated());

        return new TaskResource($task->refresh()->loadMissing('children.children'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        $ids = $task->children()->pluck('id')->push($task->id);

        if (Expense::whereIn('task_id', $ids)->exists()) {
            abort(409, 'Aktivitas ini sudah punya catatan biaya dan tidak bisa dihapus.');
        }

        DB::transaction(function () use ($task) {
            $task->children()->delete();
            $task->delete();
        });

        return response()->noContent();
    }
}