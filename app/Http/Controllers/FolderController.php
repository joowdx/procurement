<?php

namespace App\Http\Controllers;

use App\Http\Requests\Folders\DestroyFolderRequest;
use App\Http\Requests\Folders\ReorderFoldersRequest;
use App\Http\Requests\Folders\StoreFolderRequest;
use App\Http\Requests\Folders\UpdateFolderRequest;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FolderController extends Controller
{
    /**
     * Display a listing of folders.
     */
    public function index(Request $request)
    {
        // API request for folder selection (file upload)
        if ($request->wantsJson() && ! $request->has('filter')) {
            $query = Folder::query();

            if ($search = $request->get('search')) {
                $query->where('name', 'like', "%{$search}%");
            }

            $folders = $query->orderBy('path')
                ->orderBy('order')
                ->get()
                ->map(function ($folder) {
                    return [
                        'id' => $folder->id,
                        'name' => $folder->name,
                        'path' => $folder->path,
                    ];
                });

            return response()->json($folders);
        }

        // Regular page request with filters
        $filter = $request->get('filter');
        $search = $request->get('search');

        // Calculate maximum depth in database
        $maxDepthAvailable = Folder::max('depth') ?? 0;

        // Set default max_depth: 3 if available depth > 3, otherwise use max available
        $defaultMaxDepth = $maxDepthAvailable > 3 ? 3 : $maxDepthAvailable;
        $maxDepth = $request->get('max_depth', $defaultMaxDepth);

        if ($filter === 'empty') {
            // Empty folders (no files and no subfolders)
            $query = Folder::doesntHave('placements')
                ->doesntHave('children');
        } elseif ($filter === 'deleted') {
            // Soft deleted folders
            $query = Folder::onlyTrashed();
        } else {
            // All active folders
            $query = Folder::query();
        }

        // Apply search directly to path column
        if ($search) {
            $query->where('path', 'like', "%{$search}%");
        }

        // Apply depth filter
        if ($maxDepth !== null && $maxDepth !== '') {
            $query->where('depth', '<=', (int) $maxDepth);
        }

        $folders = $query->withCount(['children', 'placements'])
            ->orderBy('path')
            ->orderBy('order')
            ->paginate(20);

        // Get counts for all filters
        $counts = [
            'all' => Folder::count(),
            'empty' => Folder::doesntHave('placements')->doesntHave('children')->count(),
            'deleted' => Folder::onlyTrashed()->count(),
        ];

        return Inertia::render('folders/index', [
            'folders' => $folders,
            'filter' => $filter,
            'search' => $search,
            'max_depth' => $maxDepth,
            'max_depth_available' => $maxDepthAvailable,
            'counts' => $counts,
        ]);
    }

    /**
     * Store a newly created folder.
     */
    public function store(StoreFolderRequest $request)
    {
        $validated = $request->validated();

        $parent = $validated['parent_id'] ? Folder::find($validated['parent_id']) : null;

        $folder = Folder::create([
            'parent_id' => $validated['parent_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'depth' => $parent ? $parent->depth + 1 : 0,
            'order' => Folder::where('parent_id', $validated['parent_id'])->max('order') + 1,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        // If creating a subfolder, redirect to parent folder show page
        if ($validated['parent_id']) {
            return redirect()->route('folders.show', $validated['parent_id'])
                ->with('success', 'Folder created successfully.');
        }

        // Otherwise redirect to folders index
        return redirect()->route('folders.index')
            ->with('success', 'Folder created successfully.');
    }

    /**
     * Display the specified folder.
     */
    public function show(Request $request, Folder $folder)
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

        // Recursively load all parent relationships for breadcrumbs
        $this->loadAncestors($folder);

        return Inertia::render('folders/show', [
            'folder' => $folder,
        ]);
    }

    /**
     * Recursively load all parent relationships.
     */
    protected function loadAncestors(Folder $folder): void
    {
        $folder->load('parent');

        if ($folder->parent) {
            $this->loadAncestors($folder->parent);
        }
    }

    /**
     * Update the specified folder.
     */
    public function update(UpdateFolderRequest $request, Folder $folder)
    {
        $validated = $request->validated();

        $folder->updated_by = Auth::id();

        // If parent changes, update depth
        if (isset($validated['parent_id']) && $validated['parent_id'] !== $folder->parent_id) {
            $parent = $validated['parent_id'] ? Folder::find($validated['parent_id']) : null;
            $folder->parent_id = $validated['parent_id'];
            $folder->depth = $parent ? $parent->depth + 1 : 0;
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
     */
    public function destroy(DestroyFolderRequest $request, Folder $folder)
    {
        $folder->delete();

        return redirect()->route('folders.index')->with('success', 'Folder deleted successfully.');
    }

    /**
     * Reorder folders.
     */
    public function reorder(ReorderFoldersRequest $request)
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
