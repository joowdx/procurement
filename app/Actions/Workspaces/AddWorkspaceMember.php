<?php

namespace App\Actions\Workspaces;

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;

class AddWorkspaceMember
{
    /**
     * Add user to group with specified permissions.
     */
    public function handle(Workspace $workspace, User $user, array $permissions): Membership
    {
        // Set default permissions
        $defaultPermissions = [
            'users' => false,
            'files' => true,
            'folders' => true,
            'settings' => true,
        ];

        $finalPermissions = array_merge($defaultPermissions, $permissions);

        return Membership::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'member',
            'permissions' => $finalPermissions,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }
}
