<?php

namespace App\Policies;

use App\Models\Prospect;
use App\Models\User;

class ProspectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Prospect $prospect): bool
    {
        return $user->is_active && ($user->isAdmin() || $prospect->owner_id === $user->id);
    }

    public function update(User $user, Prospect $prospect): bool
    {
        return $this->view($user, $prospect);
    }

    public function reassign(User $user, Prospect $prospect): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
