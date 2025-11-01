<?php

namespace App\Http\Controllers;

use App\Http\Requests\Folders\DestroyFolderRequest;
use App\Http\Requests\Folders\ReorderFoldersRequest;
use App\Http\Requests\Folders\StoreFolderRequest;
use App\Http\Requests\Folders\UpdateFolderRequest;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FolderController extends Controller
{
    /**
     * Display a listing of folders.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $workspaceId = $request->get('workspace_id');

        // Check workspace access if workspace_id is provided
        if ($workspaceId) {
            $workspace = \App\Models\Workspace::findOrFail($workspaceId);

            // Check if user has access to this workspace
            if (! $this->hasWorkspaceAccess(Auth::user(), $workspace)) {
                abort(403, 'You do not have access to this workspace.');
            }
        }

        // API request for folder selection (file upload)
        if ($request->wantsJson() && ! $request->has('filter')) {
            $query = Folder::withoutDeletedAncestors();

            // Filter by workspace if provided
            if ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            }

            if ($search = $request->get('search')) {
                $query->where('name', 'like', "%{$search}%");
            }

            $folders = $query->orderBy('route')
                ->orderBy('order')
                ->get()
                ->map(function ($folder) {
                    return [
                        'id' => $folder->id,
                        'name' => $folder->name,
                        'route' => $folder->route,
                    ];
                });

            return response()->json($folders);
        }

        // Regular page request with filters
        $filter = $request->get('filter');
        $search = $request->get('search');

        // Calculate maximum level in database (filtered by workspace if applicable)
        $maxLevelQuery = $workspaceId ? Folder::where('workspace_id', $workspaceId) : Folder::query();
        $maxLevelAvailable = $maxLevelQuery->max('level');

        // Get max_level from request (null if not specified, cast to int if provided)
        $maxLevel = $request->has('max_level') ? (int) $request->get('max_level') : null;

        if ($filter === 'empty') {
            // Empty folders (no files and no subfolders, excluding those with deleted ancestors)
            $query = Folder::withoutDeletedAncestors()
                ->doesntHave('placements')
                ->doesntHave('children');
        } elseif ($filter === 'deleted') {
            // ALL soft deleted folders (including those with deleted ancestors)
            $query = Folder::onlyTrashed();
        } else {
            // All active folders (excluding those with deleted ancestors)
            $query = Folder::withoutDeletedAncestors();
        }

        // Apply search (skip for deleted filter as it doesn't use route column reliably)
        if ($search && $filter !== 'deleted') {
            $query->where('route', 'like', "%{$search}%");
        }

        // Apply level filter (skip for deleted filter)
        if ($maxLevel !== null && $maxLevel !== '' && $filter !== 'deleted') {
            $query->where('level', '<=', (int) $maxLevel);
        }

        // Filter by workspace if provided
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        $folders = $query->withCount(['children', 'placements'])
            ->orderBy('route')
            ->orderBy('order')
            ->paginate(20);

        // Get counts for all filters (filtered by workspace if applicable)
        $countsBaseQuery = $workspaceId ? Folder::where('workspace_id', $workspaceId) : Folder::query();
        $counts = [
            'all' => (clone $countsBaseQuery)->withoutDeletedAncestors()->count(),
            'empty' => (clone $countsBaseQuery)->withoutDeletedAncestors()->doesntHave('placements')->doesntHave('children')->count(),
            'deleted' => (clone $countsBaseQuery)->onlyTrashed()->count(),
        ];

        return Inertia::render('folders/index', [
            'folders' => $folders,
            'filter' => $filter,
            'search' => $search,
            'max_level' => $maxLevel,
            'max_level_available' => $maxLevelAvailable,
            'counts' => $counts,
        ]);
    }

    /**
     * Check if user has access to workspace.
     */
    private function hasWorkspaceAccess($user, \App\Models\Workspace $workspace): bool
    {
        // Workspace owner always has access
        if ($workspace->user_id === $user->id) {
            return true;
        }

        // Root users have access to all workspaces
        if ($user->role === 'root') {
            return true;
        }

        // Check if user is a member
        return $user->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    /**
     * Store a newly created folder.
     */
    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $parentId = $validated['parent_id'] ?? null;
        $parent = $parentId ? Folder::find($parentId) : null;

        $folder = Folder::create([
            'workspace_id' => $validated['workspace_id'],
            'parent_id' => $parentId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'level' => $parent ? $parent->level + 1 : 0,
            'order' => Folder::where('parent_id', $parentId)->max('order') + 1,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        // If creating a subfolder, redirect to parent folder show page
        if ($parentId) {
            return redirect()->route('folders.show', $parentId)
                ->with('success', 'Folder created successfully.');
        }

        // Otherwise redirect back (preserves filters)
        return redirect()->back()->with('success', 'Folder created successfully.');
    }

    /**
     * Display the specified folder.
     */
    public function show(Request $request, Folder $folder): Response|JsonResponse
    {
        // Check if folder is deleted
        if ($folder->trashed()) {
            abort(404);
        }

        // Check workspace access
        if ($folder->workspace_id && ! $this->hasWorkspaceAccess(Auth::user(), $folder->workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // API request for lazy loading children
        if ($request->wantsJson() || $request->has('children')) {
            $children = Folder::where('parent_id', $folder->id)
                ->withCount('children')
                ->orderBy('order')
                ->get();

            return response()->json($children);
        }

        // Regular page request
        // Fresh load to ensure we get latest data
        $folder->loadCount(['children', 'placements']);
        $folder->load([
            'children' => function ($query) {
                $query->withCount('children')->orderBy('order');
            },
            'placements',
            'files.version',
        ]);

        // Load all ancestors using the package (including trashed)
        $folder->loadMissing(['ancestors' => function ($query) {
            $query->withTrashed();
        }]);

        return Inertia::render('folders/show', [
            'folder' => $folder,
        ]);
    }

    /**
     * Update the specified folder.
     */
    public function update(UpdateFolderRequest $request, Folder $folder): RedirectResponse
    {
        $validated = $request->validated();

        $folder->updated_by = Auth::id();

        // If parent changes, update level
        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== $folder->parent_id) {
            $parent = $validated['parent_id'] ? Folder::find($validated['parent_id']) : null;
            $folder->parent_id = $validated['parent_id'];
            $folder->level = $parent ? $parent->level + 1 : 0;
        }

        if (isset($validated['name'])) {
            $folder->name = $validated['name'];
        }

        if (array_key_exists('description', $validated)) {
            $folder->description = $validated['description'];
        }

        $folder->save();

        return redirect()->back()->with('success', 'Folder updated successfully.');
    }

    /**
     * Remove the specified folder.
     * If already trashed, force delete with password confirmation.
     * Otherwise, soft delete.
     */
    public function destroy(DestroyFolderRequest $request, Folder $folder): RedirectResponse
    {
        if ($folder->trashed()) {
            $folder->forceDelete();

            return redirect()->back()->with('success', 'Folder permanently deleted.');
        }

        // Soft delete the folder
        $folder->delete();

        // Cascade soft delete to all descendants
        $this->cascadeDeleteDescendants($folder);

        return redirect()->back()->with('success', 'Folder deleted successfully.');
    }

    /**
     * Recursively soft delete all descendants.
     */
    private function cascadeDeleteDescendants(Folder $folder): void
    {
        $children = Folder::where('parent_id', $folder->id)->get();

        foreach ($children as $child) {
            $child->delete();
            $this->cascadeDeleteDescendants($child);
        }
    }

    /**
     * Restore a soft-deleted folder.
     */
    public function restore(Request $request, string $id): RedirectResponse
    {
        $folder = Folder::withTrashed()->findOrFail($id);

        // Check workspace access
        if ($folder->workspace_id && ! $this->hasWorkspaceAccess(Auth::user(), $folder->workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $folder->restore();
        $folder->deleted_by = null;
        $folder->save();

        // Cascade restore to all descendants
        $this->cascadeRestoreDescendants($folder);

        return redirect()->back()->with('success', 'Folder restored successfully.');
    }

    /**
     * Recursively restore all descendants.
     */
    private function cascadeRestoreDescendants(Folder $folder): void
    {
        $children = Folder::withTrashed()->where('parent_id', $folder->id)->get();

        foreach ($children as $child) {
            if ($child->trashed()) {
                $child->restore();
                $child->deleted_by = null;
                $child->save();
                $this->cascadeRestoreDescendants($child);
            }
        }
    }

    /**
     * Reorder folders.
     */
    public function reorder(ReorderFoldersRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            foreach ($validated['folders'] as $folderData) {
                Folder::where('id', $folderData['id'])
                    ->update([
                        'order' => $folderData['order'],
                        'updated_by' => Auth::id(),
                    ]);
            }
        });

        return redirect()->back()->with('success', 'Folders reordered successfully.');
    }
}
