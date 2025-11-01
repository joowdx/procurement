<?php

namespace App\Repositories;

use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

class FolderRepository
{
    /**
     * Get user's folders with optional workspace filtering (paginated).
     */
    public function getUserFoldersPaginated(User $user, ?string $workspaceId = null, array $filters = [], int $perPage = 20)
    {
        $query = Folder::query();

        // If workspace_id specified, filter by workspace
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        } else {
            // Get folders from all workspaces user has access to
            $userWorkspaceIds = $this->getUserWorkspaceIds($user);
            $query->whereIn('workspace_id', $userWorkspaceIds);
        }

        // Apply filters
        if (isset($filters['search'])) {
            $query->where('route', 'like', "%{$filters['search']}%");
        }

        if (isset($filters['max_level'])) {
            $query->where('level', '<=', (int) $filters['max_level']);
        }

        if (isset($filters['filter'])) {
            switch ($filters['filter']) {
                case 'empty':
                    $query->doesntHave('placements')
                        ->doesntHave('children')
                        ->whereNull('parent_id'); // Only root folders can be empty
                    break;
                case 'deleted':
                    $query->onlyTrashed();
                    break;
                default:
                    $query->whereNull('deleted_at');
            }
        } else {
            $query->whereNull('deleted_at');
        }

        return $query->withCount(['children', 'placements'])
            ->orderBy('route')
            ->orderBy('order')
            ->paginate($perPage);
    }

    /**
     * Get maximum folder level for a workspace.
     */
    public function getMaxLevel(?string $workspaceId = null): int
    {
        $query = Folder::query();

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        return $query->whereNull('deleted_at')->max('level') ?? 0;
    }

    /**
     * Get user's folders with optional workspace filtering (non-paginated for API).
     */
    public function getUserFolders(User $user, ?string $workspaceId = null, array $filters = []): Collection
    {
        $query = Folder::query();

        // If workspace_id specified, filter by workspace
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        } else {
            // Get folders from all workspaces user has access to
            $userWorkspaceIds = $this->getUserWorkspaceIds($user);
            $query->whereIn('workspace_id', $userWorkspaceIds);
        }

        // Apply filters
        if (isset($filters['search'])) {
            $query->where('route', 'like', "%{$filters['search']}%");
        }

        if (isset($filters['max_level'])) {
            $query->where('level', '<=', (int) $filters['max_level']);
        }

        if (isset($filters['filter'])) {
            switch ($filters['filter']) {
                case 'empty':
                    $query->doesntHave('placements')
                        ->doesntHave('children')
                        ->whereNull('parent_id'); // Only root folders can be empty
                    break;
                case 'deleted':
                    $query->onlyTrashed();
                    break;
                default:
                    $query->whereNull('deleted_at');
            }
        } else {
            $query->whereNull('deleted_at');
        }

        return $query->withCount(['children', 'placements'])
            ->orderBy('route')
            ->orderBy('order')
            ->get();
    }

    /**
     * Get folder counts for a workspace.
     */
    public function getFolderCounts(string $workspaceId): array
    {
        return [
            'all' => Folder::where('workspace_id', $workspaceId)->whereNull('deleted_at')->count(),
            'empty' => Folder::where('workspace_id', $workspaceId)
                ->whereNull('deleted_at')
                ->doesntHave('placements')
                ->doesntHave('children')
                ->count(),
            'deleted' => Folder::where('workspace_id', $workspaceId)->onlyTrashed()->count(),
        ];
    }

    /**
     * Get folder tree for a workspace.
     */
    public function getFolderTree(string $workspaceId, ?int $maxDepth = null): Collection
    {
        $query = Folder::where('workspace_id', $workspaceId)
            ->whereNull('deleted_at');

        if ($maxDepth !== null) {
            $query->where('level', '<=', $maxDepth);
        }

        return $query->withCount('children')
            ->orderBy('route')
            ->orderBy('order')
            ->get();
    }

    /**
     * Get workspace IDs user has access to.
     */
    private function getUserWorkspaceIds(User $user): array
    {
        // Root users can access all workspaces
        if ($user->role === 'root') {
            return Workspace::pluck('id')->toArray();
        }

        // Get owned workspaces
        $ownedWorkspaceIds = Workspace::where('user_id', $user->id)->pluck('id')->toArray();

        // Get member workspaces
        $memberWorkspaceIds = $user->workspaces()->pluck('workspaces.id')->toArray();

        return array_unique(array_merge($ownedWorkspaceIds, $memberWorkspaceIds));
    }
}
