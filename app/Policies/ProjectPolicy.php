<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /** Owners see everything; others only what is assigned to them. */
    public function view(User $user, Project $project): bool
    {
        return $user->isOwner() || $project->users()->whereKey($user->id)->exists();
    }

    /** Assigned team members can edit and post; client-portal users are read-only. */
    public function update(User $user, Project $project): bool
    {
        return $user->isStaff() && $this->view($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }
}
