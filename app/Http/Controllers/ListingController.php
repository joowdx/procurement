<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    /**
     * Display the main listing page with all level 0 folders.
     */
    public function index(): Response
    {
        // Get all level 0 folders (root folders)
        $rootFolders = Folder::withoutDeletedAncestors()
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get()
            ->map(fn ($folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
            ]);

        return Inertia::render('listing/index', [
            'rootFolders' => $rootFolders,
        ]);
    }

    /**
     * Display a specific folder with its contents.
     */
    public function show(Request $request, Folder $folder): Response
    {
        // Load all descendants recursively with their files
        $descendants = $folder->descendants()
            ->withoutDeletedAncestors()
            ->with(['files.version'])
            ->withCount(['children', 'placements'])
            ->orderBy('route')
            ->get();

        // Load direct files of this folder
        $folder->load(['files.version']);

        // Load active ancestors for breadcrumbs (exclude deleted)
        $folder->loadMissing(['ancestors']);

        // Get all level 0 folders for navigation
        $rootFolders = Folder::withoutDeletedAncestors()
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        return Inertia::render('listing/show', [
            'folder' => $folder,
            'descendants' => $descendants,
            'rootFolders' => $rootFolders,
        ]);
    }
}
