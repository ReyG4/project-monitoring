<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Operator;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Operator;
    }

    public function update(User $user, User $target): bool
    {
        return $user->role === Role::Operator;
    }
}