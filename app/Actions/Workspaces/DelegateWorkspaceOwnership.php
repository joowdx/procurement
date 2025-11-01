<?php

namespace App\Actions\Workspaces;

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;

class DelegateWorkspaceOwnership
{
    /**
     * Delegate group ownership to another user.
     */
    public function handle(Workspace $workspace, User $newOwner): Workspace
    {
        $oldOwner = $workspace->owner;

        // Update group ownership
        $workspace->user_id = $newOwner->id;
        $workspace->save();

        // Upsert old owner membership with all permissions
        Membership::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $oldOwner->id,
            ],
            [
                'permissions' => [
                    'users' => true,
                    'files' => true,
                    'folders' => true,
                    'settings' => true,
                ],
                'joined_at' => now(),
            ]
        );

        // Upsert new owner membership with all permissions
        Membership::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $newOwner->id,
            ],
            [
                'permissions' => [
                    'users' => true,
                    'files' => true,
                    'folders' => true,
                    'settings' => true,
                ],
                'joined_at' => now(),
            ]
        );

        return $workspace;
    }
}
