<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return $user->can('view', $expense->task->project);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can('update', $expense->task->project);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->can('update', $expense->task->project);
    }
}