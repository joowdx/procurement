<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;

class RemoveWorkspaceMember
{
    /**
     * Remove user from group.
     */
    public function handle(Workspace $workspace, User $user): bool
    {
        // Cannot remove group owner
        if ($workspace->user_id === $user->id) {
            return false;
        }

        $detached = $workspace->users()->detach($user->id);

        return $detached > 0 || $detached === 0; // Return true even if no rows affected
    }
}
