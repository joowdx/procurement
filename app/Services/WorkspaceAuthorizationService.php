<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;

class WorkspaceAuthorizationService
{
    /**
     * Check if user can access a group.
     */
    public function canAccessWorkspace(User $user, Workspace $workspace): bool
    {
        // Root users always have access
        if ($user->role === 'root') {
            return true;
        }

        // Workspace owner always has access
        if ($workspace->user_id === $user->id) {
            return true;
        }

        // Check if user is a member
        return $workspace->users()->wherePivot('user_id', $user->id)->exists();
    }

    /**
     * Check if user has specific permission in group.
     */
    public function hasPermission(User $user, Workspace $workspace, string $permission): bool
    {
        // Root users always have all permissions
        if ($user->role === 'root') {
            return true;
        }

        // Workspace owner always has all permissions
        if ($workspace->user_id === $user->id) {
            return true;
        }

        // Check member permissions
        $membership = Membership::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return false;
        }

        $permissions = $membership->permissions ?? [];

        // Default permissions: users=false, others=true
        return $permissions[$permission] ?? ($permission !== 'users');
    }

    /**
     * Ensure user has access to group, throw exception if not.
     */
    public function ensureWorkspaceAccess(User $user, Workspace $workspace, ?string $permission = null): void
    {
        if (! $this->canAccessWorkspace($user, $workspace)) {
            abort(403, 'You do not have access to this group.');
        }

        // Auto-create membership for group owner
        if ($workspace->user_id === $user->id) {
            $this->autoCreateOwnerMembership($workspace, $user);
        }

        // Check specific permission if required
        if ($permission && ! $this->hasPermission($user, $workspace, $permission)) {
            abort(403, "You do not have permission to manage {$permission} in this group.");
        }
    }

    /**
     * Auto-create membership record for workspace owner.
     */
    public function autoCreateOwnerMembership(Workspace $workspace, User $user): void
    {
        Membership::firstOrCreate([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ], [
            'role' => 'owner',
            'permissions' => [
                'users' => true,
                'files' => true,
                'folders' => true,
                'settings' => true,
            ],
            'joined_at' => now(),
        ]);
    }
}
