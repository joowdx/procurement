<?php

namespace App\Actions\Workspaces;

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;

class UpdateMemberPermissions
{
    /**
     * Update member's permissions in group.
     */
    public function handle(Workspace $workspace, User $user, array $permissions): Membership
    {
        // Cannot modify group owner permissions
        if ($workspace->user_id === $user->id) {
            throw new \Exception('Cannot modify group owner permissions.');
        }

        $membership = Membership::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            // Create membership if it doesn't exist
            $membership = Membership::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'member',
                'permissions' => $permissions,
                'joined_at' => now(),
            ]);
        } else {
            $membership->update(['permissions' => $permissions]);
        }

        return $membership;
    }
}
