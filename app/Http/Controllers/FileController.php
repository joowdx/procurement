<?php

namespace App\Http\Controllers;

use App\Http\Requests\Files\DestroyFileRequest;
use App\Http\Requests\Files\StoreFileRequest;
use App\Http\Requests\Files\UpdateFileRequest;
use App\Models\File;
use App\Models\Folder;
use App\Models\Version;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /**
     * Display a listing of files.
     */
    public function index(Request $request): Response
    {
        $filter = $request->get('filter');

        if ($filter === 'unplaced') {
            // Files with no folders (active files only)
            $query = File::with(['version', 'versions', 'folders', 'tags'])
                ->withSum('versions', 'downloads')
                ->doesntHave('placements');
        } elseif ($filter === 'deleted') {
            // Soft deleted files
            $query = File::with(['version', 'versions'])
                ->withSum('versions', 'downloads')
                ->onlyTrashed();
        } else {
            // All active files (excluding deleted)
            $query = File::with(['version', 'versions', 'folders', 'tags'])
                ->withSum('versions', 'downloads');
        }

        $files = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get counts for all filters
        $counts = [
            'all' => File::count(),
            'unplaced' => File::doesntHave('placements')->count(),
            'deleted' => File::onlyTrashed()->count(),
        ];

        return Inertia::render('files/index', [
            'files' => $files,
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    /**
     * Store a newly created file.
     */
    public function store(StoreFileRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $disk = $validated['disk'];

        // Handle based on disk type
        if ($disk === 'external') {
            // External file - no upload needed
            $hash = hash('sha256', $validated['path']);
            $path = $validated['path'];
            $size = 0;
            $type = 'application/octet-stream';
            $extension = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'url';
        } else {
            // Local file - handle upload
            $uploadedFile = $request->file('file');
            $hash = hash_file('sha256', $uploadedFile->getRealPath());

            // Check if file with same hash already exists
            $existingVersion = Version::where('hash', $hash)->first();
            if ($existingVersion) {
                return redirect()->back()->withErrors([
                    'file' => 'This file already exists in the system.',
                ]);
            }

            // Store the file using hash-based path
            $hashPath = substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
            $path = $uploadedFile->storeAs($hashPath, $uploadedFile->getClientOriginalName(), 'local');
            $size = $uploadedFile->getSize();
            $type = $uploadedFile->getMimeType();
            $extension = $uploadedFile->getClientOriginalExtension();
        }

        // Create file record
        $file = File::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $type,
            'extension' => $extension,
            'locked' => false,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        // Create first version
        Version::create([
            'file_id' => $file->id,
            'number' => 1,
            'hash' => $hash,
            'disk' => $disk,
            'path' => $path,
            'size' => $size,
            'created_by' => Auth::id(),
        ]);

        // Attach to folder if specified (single folder from folder page)
        if ($validated['folder_id'] ?? null) {
            $file->folders()->attach($validated['folder_id'], [
                'order' => $file->placements()->where('folder_id', $validated['folder_id'])->max('order') + 1,
                'created_at' => now(),
            ]);
        }

        // Attach to multiple folders if specified (from files page)
        if (! empty($validated['folder_ids'])) {
            foreach ($validated['folder_ids'] as $folderId) {
                $file->folders()->attach($folderId, [
                    'order' => $file->placements()->where('folder_id', $folderId)->max('order') + 1,
                    'created_at' => now(),
                ]);
            }
        }

        return redirect()->back()->with('success', 'File uploaded successfully.');
    }

    /**
     * Display the specified file.
     */
    public function show(File $file): Response
    {
        $file->load(['version', 'versions', 'folders', 'tags', 'comments.creator']);
        $file->loadSum('versions', 'downloads');

        return Inertia::render('files/show', [
            'file' => $file,
        ]);
    }

    /**
     * Update the specified file.
     */
    public function update(UpdateFileRequest $request, string $id): RedirectResponse
    {
        // Handle restore for soft-deleted files
        if ($request->has('restore')) {
            $file = File::withTrashed()->findOrFail($id);
            $file->restore();
            $file->deleted_by = null;
            $file->save();

            return redirect()->back()->with('success', 'File restored successfully.');
        }

        $file = File::findOrFail($id);

        if ($file->locked) {
            return redirect()->back()->withErrors([
                'file' => 'This file is locked and cannot be modified.',
            ]);
        }

        $validated = $request->validated();
        $disk = $validated['disk'] ?? 'local';

        $file->updated_by = Auth::id();

        if (isset($validated['name'])) {
            $file->name = $validated['name'];
        }

        if (array_key_exists('description', $validated)) {
            $file->description = $validated['description'];
        }

        // Handle file replacement
        $hasNewVersion = false;

        if ($disk === 'external' && isset($validated['path'])) {
            // External file update
            $hash = hash('sha256', $validated['path']);

            if ($file->hash === $hash) {
                return redirect()->back()->withErrors([
                    'file' => 'This URL is identical to the current version.',
                ]);
            }

            $path = $validated['path'];
            $size = 0;
            $type = 'application/octet-stream';
            $extension = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'url';
            $hasNewVersion = true;
        } elseif ($request->hasFile('file')) {
            // Local file update
            $uploadedFile = $request->file('file');
            $hash = hash_file('sha256', $uploadedFile->getRealPath());

            if ($file->hash === $hash) {
                return redirect()->back()->withErrors([
                    'file' => 'This file is identical to the current version.',
                ]);
            }

            $hashPath = substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
            $path = $uploadedFile->storeAs($hashPath, $uploadedFile->getClientOriginalName(), 'local');
            $size = $uploadedFile->getSize();
            $type = $uploadedFile->getMimeType();
            $extension = $uploadedFile->getClientOriginalExtension();
            $hasNewVersion = true;
        }

        if ($hasNewVersion) {
            // Create new version
            $lastVersion = $file->versions()->orderBy('number', 'desc')->first();
            Version::create([
                'file_id' => $file->id,
                'number' => $lastVersion->number + 1,
                'hash' => $hash,
                'disk' => $disk,
                'path' => $path,
                'size' => $size,
                'created_by' => Auth::id(),
            ]);

            $file->type = $type;
            $file->extension = $extension;
        }

        $file->save();

        return redirect()->back()->with('success', 'File updated successfully.');
    }

    /**
     * Remove the specified file.
     * If already trashed, force delete with password confirmation.
     * Otherwise, soft delete.
     */
    public function destroy(DestroyFileRequest $request, File $file): RedirectResponse
    {
        if ($file->trashed()) {
            $file->forceDelete();

            return redirect()->back()->with('success', 'File permanently deleted.');
        }

        $file->delete();

        return redirect()->back()->with('success', 'File deleted successfully.');
    }

    /**
     * Download the specified file.
     */
    public function download(File $file): StreamedResponse|RedirectResponse
    {
        $version = $file->version;

        if (! $version) {
            abort(404, 'File version not found.');
        }

        // Increment download count
        $version->increment('downloads');

        if ($version->disk === 'external') {
            return redirect($version->path);
        }

        return Storage::disk($version->disk)->download($version->path, $file->name.'.'.$file->extension);
    }

    /**
     * Preview the specified file inline.
     */
    public function preview(File $file): StreamedResponse|RedirectResponse
    {
        $version = $file->version;

        if (! $version) {
            abort(404, 'File version not found.');
        }

        if ($version->disk === 'external') {
            return redirect($version->path);
        }

        return Storage::disk($version->disk)->response($version->path, $file->name.'.'.$file->extension, [
            'Content-Type' => $file->type,
            'Content-Disposition' => 'inline; filename="'.$file->name.'.'.$file->extension.'"',
        ]);
    }
}
