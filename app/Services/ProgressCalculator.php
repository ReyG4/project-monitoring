<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

class ProgressCalculator
{
    /**
     * Create a new class instance.
     */

    public function forTask(Task $task):float{
        return round($this->compute($task), 2);
    }

    public function forProject(Project $project): float{
        $roots = $project->tasks->whereNull('parent_task_id');
        return round($this->weightedSum($roots), 2);
    }

    private function compute(Task $task): float{
        $children = $task->children;
        if($children->isEmpty()){
            return (float)$task->progress;
        }

        return $this->weightedSum($children);
    }

    private function weightedSum(Collection $tasks): float{
        return (float)$tasks->sum(
            fn (Task $task) => (float)$task->weight * $this->compute($task)/100
        );
    }

    public function __construct()
    {
        //
    }
}