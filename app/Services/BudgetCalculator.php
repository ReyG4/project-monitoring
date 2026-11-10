<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

class BudgetCalculator
{
    /**
     * Create a new class instance.
     */

    public function forTask (Task $task): BudgetSummary{
        $children = $task->children;

        if($children->isEmpty()){
            return new BudgetSummary(
                (float) $task->budget_plan,
                (float) $task->expenses->sum('amount'),
            );
        }
        return $this->combine($children);
    }

    public function forProject(Project $project): BudgetSummary{
        return $this->combine($project->tasks->whereNull('parent_task_id'));
    }

    private function combine(Collection $tasks): BudgetSummary{
        $plan = 0.0;
        $actual = 0.0;

        foreach($tasks as $task){
            $summary = $this->forTask($task);
            $plan += $summary->plan;
            $actual += $summary->actual;
        }
        return new BudgetSummary($plan, $actual);
    }

    public function __construct()
    {
        //
    }
}