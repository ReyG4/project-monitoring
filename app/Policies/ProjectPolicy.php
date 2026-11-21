<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Enums\ProjectStatus;
use App\Enums\Role;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::ProjectManager, Role::Tester], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
    {
        return Project::visibleTo($user)->whereKey($project->getKey())->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === Role::ProjectManager;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->owns($user, $project) && $project->status->isEditable();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->owns($user, $project) && $project->status !== ProjectStatus::Submitted;
    }

    private function owns(User $user, Project $project): bool{
        return $user->role === Role::ProjectManager && $project->created_by === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }

    public function activate(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function submit(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function review(User $user, Project $project): bool
    {
        return $user->role === Role::Tester && $this->view($user, $project);
    }
}