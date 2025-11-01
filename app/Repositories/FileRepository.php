<?php

namespace App\Repositories;

use App\Models\File;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

class FileRepository
{
    /**
     * Get user's files with optional group filtering.
     */
    public function getUserFiles(User $user, ?string $workspaceId = null, array $filters = []): Collection
    {
        $query = File::query();

        // If workspace_id specified, filter by workspace
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        } else {
            // Get files from all workspaces user has access to
            $userWorkspaceIds = $this->getUserWorkspaceIds($user);
            $query->whereIn('workspace_id', $userWorkspaceIds);
        }

        // Apply filters
        if (isset($filters['filter'])) {
            switch ($filters['filter']) {
                case 'unplaced':
                    $query->doesntHave('placements');
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

        // Apply type filter
        if (isset($filters['type'])) {
            $query->where('type', 'like', $filters['type'].'%');
        }

        return $query->with(['version', 'versions', 'folders', 'tags'])
            ->withSum('versions', 'downloads')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get user's files with optional workspace filtering (paginated).
     */
    public function getUserFilesPaginated(User $user, ?string $workspaceId = null, array $filters = [], int $perPage = 20)
    {
        $query = File::query();

        // If workspace_id specified, filter by workspace
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        } else {
            // Get files from all workspaces user has access to
            $userWorkspaceIds = $this->getUserWorkspaceIds($user);
            $query->whereIn('workspace_id', $userWorkspaceIds);
        }

        // Apply filters
        if (isset($filters['filter'])) {
            switch ($filters['filter']) {
                case 'unplaced':
                    $query->doesntHave('placements');
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

        // Apply type filter
        if (isset($filters['type'])) {
            $query->where('type', 'like', $filters['type'].'%');
        }

        return $query->with(['version', 'versions', 'folders', 'tags'])
            ->withSum('versions', 'downloads')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get file counts for a workspace.
     */
    public function getFileCounts(string $workspaceId): array
    {
        return [
            'all' => File::where('workspace_id', $workspaceId)->whereNull('deleted_at')->count(),
            'unplaced' => File::where('workspace_id', $workspaceId)
                ->whereNull('deleted_at')
                ->doesntHave('placements')
                ->count(),
            'deleted' => File::where('workspace_id', $workspaceId)->onlyTrashed()->count(),
        ];
    }

    /**
     * Check if file hash already exists in workspace.
     */
    public function checkHashDuplication(string $hash, string $workspaceId): ?File
    {
        return File::where('workspace_id', $workspaceId)
            ->whereHas('version', function ($query) use ($hash) {
                $query->where('hash', $hash);
            })
            ->whereNull('deleted_at')
            ->first();
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
