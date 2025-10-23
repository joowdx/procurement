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
        // API request for folder selection (file upload)
        if ($request->wantsJson() && ! $request->has('filter')) {
            $query = Folder::withoutDeletedAncestors();

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

        // Calculate maximum level in database
        $maxLevelAvailable = Folder::max('level') ?? 0;

        // Set default max_level: 3 if available level > 3, otherwise use max available
        $defaultMaxLevel = $maxLevelAvailable > 3 ? 3 : $maxLevelAvailable;
        $maxLevel = $request->get('max_level', $defaultMaxLevel);

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

        $folders = $query->withCount(['children', 'placements'])
            ->orderBy('route')
            ->orderBy('order')
            ->paginate(20);

        // Get counts for all filters
        $counts = [
            'all' => Folder::withoutDeletedAncestors()->count(),
            'empty' => Folder::withoutDeletedAncestors()->doesntHave('placements')->doesntHave('children')->count(),
            'deleted' => Folder::onlyTrashed()->count(),
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
     * Store a newly created folder.
     */
    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $parent = $validated['parent_id'] ? Folder::find($validated['parent_id']) : null;

        $folder = Folder::create([
            'parent_id' => $validated['parent_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'level' => $parent ? $parent->level + 1 : 0,
            'order' => Folder::where('parent_id', $validated['parent_id'])->max('order') + 1,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        // If creating a subfolder, redirect to parent folder show page
        if ($validated['parent_id']) {
            return redirect()->route('folders.show', $validated['parent_id'])
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
        $folder = $folder->fresh([
            'children' => function ($query) {
                $query->withCount('children')->orderBy('order');
            },
            'files' => function ($query) {
                $query->withSum('versions', 'downloads');
            },
            'placements',
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
        if (isset($validated['parent_id']) && $validated['parent_id'] !== $folder->parent_id) {
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

        $folder->delete();

        return redirect()->back()->with('success', 'Folder deleted successfully.');
    }

    /**
     * Restore a soft-deleted folder.
     */
    public function restore(Request $request, string $id): RedirectResponse
    {
        $folder = Folder::withTrashed()->findOrFail($id);
        $folder->restore();
        $folder->deleted_by = null;
        $folder->save();

        return redirect()->back()->with('success', 'Folder restored successfully.');
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

        return back();
    }
}
