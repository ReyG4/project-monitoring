<?php

namespace Tests\Concerns;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

trait CreatesProjectData
{
    protected function makeUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    protected function makeProject(User $owner, ProjectStatus $status = ProjectStatus::Planning): Project
    {
        $project = new Project(['name' => 'Proyek Uji']);
        $project->created_by = $owner->id;
        $project->status = $status;
        $project->save();

        return $project;
    }

    protected function makeTask(Project $project, array $attributes = [], ?Task $parent = null): Task
    {
        $task = new Task(array_merge(['name' => 'Tugas Uji'], $attributes));
        $task->project_id = $project->id;
        $task->parent_task_id = $parent?->id;
        $task->save();

        return $task;
    }

    protected function makeExpense(Task $task, User $by, float $amount = 1000): Expense
    {
        $expense = new Expense(['expense_date' => '2026-11-10', 'description' => 'Biaya uji', 'amount' => $amount]);
        $expense->task_id = $task->id;
        $expense->recorded_by = $by->id;
        $expense->save();

        return $expense;
    }
}