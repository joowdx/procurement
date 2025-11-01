<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;

class DeleteWorkspace
{
    /**
     * Delete group (soft or force).
     */
    public function handle(Workspace $workspace, bool $force = false, ?User $user = null): bool
    {
        if ($force) {
            // Force delete - remove all related data
            $workspace->folders()->forceDelete();
            $workspace->files()->forceDelete();
            $workspace->users()->detach();

            return $workspace->forceDelete();
        }

        // Soft delete - the model's booted method will set deleted_by
        return $workspace->delete();
    }
}
