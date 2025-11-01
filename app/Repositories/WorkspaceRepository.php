<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceRepository
{
    /**
     * Get all workspaces user has access to (owned + member of).
     */
    public function getUserWorkspaces(User $user): Collection
    {
        // Root users can see all workspaces
        if ($user->role === 'root') {
            return Workspace::withCount(['users', 'folders', 'files'])
                ->orderBy('name')
                ->get();
        }

        // Get workspaces user owns
        $ownedWorkspaces = Workspace::where('user_id', $user->id)
            ->withCount(['users', 'folders', 'files'])
            ->get();

        // Get workspaces user is member of
        $memberWorkspaces = $user->workspaces()
            ->withCount(['users', 'folders', 'files'])
            ->get();

        // Merge and deduplicate
        return $ownedWorkspaces->merge($memberWorkspaces)->unique('id');
    }

    /**
     * Get group with members and their permissions.
     */
    public function getWorkspaceWithMembers(Workspace $workspace): Workspace
    {
        return $workspace->load([
            'members' => function ($query) {
                $query->withPivot('role', 'permissions', 'joined_at')
                    ->orderBy('pivot_joined_at');
            },
            'owner',
        ]);
    }

    /**
     * Alias for getWorkspaceWithMembers for consistency.
     */
    public function getGroupWithMembers(Workspace $workspace): Workspace
    {
        return $this->getWorkspaceWithMembers($workspace);
    }

    /**
     * Alias for getUserWorkspaces for consistency.
     */
    public function getUserGroups(User $user)
    {
        return $this->getUserWorkspaces($user);
    }

    /**
     * Get group statistics.
     */
    public function getWorkspaceStats(Workspace $workspace): array
    {
        return [
            'member_count' => $workspace->users()->count(),
            'folder_count' => $workspace->folders()->count(),
            'file_count' => $workspace->files()->count(),
            'active_folders' => $workspace->folders()->whereNull('deleted_at')->count(),
            'active_files' => $workspace->files()->whereNull('deleted_at')->count(),
            'total_size' => $workspace->files()
                ->whereHas('version')
                ->with('version')
                ->get()
                ->sum('version.size'),
        ];
    }

    /**
     * Alias for getWorkspaceStats for consistency.
     */
    public function getGroupStats(Workspace $workspace): array
    {
        return $this->getWorkspaceStats($workspace);
    }
}
