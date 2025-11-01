<?php

namespace App\Http\Controllers;

use App\Http\Requests\Files\DestroyFileRequest;
use App\Http\Requests\Files\StoreFileRequest;
use App\Http\Requests\Files\UpdateFileRequest;
use App\Models\File;
use App\Models\Folder;
use App\Models\Placement;
use App\Models\Version;
use App\Models\Workspace;
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
        $workspaceId = $request->get('workspace_id');

        // Check workspace access if workspace_id is provided
        if ($workspaceId) {
            $workspace = Workspace::findOrFail($workspaceId);

            // Check if user has access to this workspace
            if (! $this->hasWorkspaceAccess(Auth::user(), $workspace)) {
                abort(403, 'You do not have access to this workspace.');
            }
        }

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

        // Filter by workspace if workspace_id is provided
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        $files = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get counts for all filters (filtered by workspace if applicable)
        $countsQuery = $workspaceId ? File::where('workspace_id', $workspaceId) : File::query();
        $counts = [
            'all' => (clone $countsQuery)->count(),
            'unplaced' => (clone $countsQuery)->doesntHave('placements')->count(),
            'deleted' => (clone $countsQuery)->onlyTrashed()->count(),
        ];

        return Inertia::render('files/index', [
            'files' => $files,
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    /**
     * Check if user has access to workspace.
     */
    private function hasWorkspaceAccess($user, Workspace $workspace): bool
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
     * Store a newly created file.
     */
    public function store(StoreFileRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $disk = $validated['disk'];

        // Handle based on disk type
        if ($disk === 'external') {
            // External file - fetch metadata without storing locally
            $path = $validated['path'];

            try {
                // Download file using Laravel HTTP client
                $response = \Illuminate\Support\Facades\Http::timeout(60)
                    ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                    ->get($path);

                if (! $response->successful()) {
                    return redirect()->back()->withErrors([
                        'path' => 'Unable to download the external file (HTTP '.$response->status().').',
                    ]);
                }

                // Save to temporary file for hash calculation
                $tempFile = tempnam(sys_get_temp_dir(), 'ext_file_');
                file_put_contents($tempFile, $response->body());

                // Calculate hash and size
                $hash = hash_file('sha256', $tempFile);
                $size = filesize($tempFile);

                // Get MIME type from downloaded content
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $type = finfo_file($finfo, $tempFile) ?: 'application/octet-stream';
                finfo_close($finfo);

                // Clean up temp file
                unlink($tempFile);

                // Get extension from URL or MIME type
                $extension = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (! $extension) {
                    // Fallback: derive from MIME type
                    $mimeToExt = [
                        'application/pdf' => 'pdf',
                        'application/msword' => 'doc',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                        'application/vnd.ms-excel' => 'xls',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                    ];
                    $extension = $mimeToExt[$type] ?? 'bin';
                }

                // Check if file with same hash already exists in this workspace
                $existingVersion = Version::where('hash', $hash)
                    ->whereHas('file', fn ($q) => $q->where('workspace_id', $validated['workspace_id']))
                    ->first();
                if ($existingVersion) {
                    return redirect()->back()->withErrors([
                        'path' => 'A file with identical content already exists in this workspace.',
                    ]);
                }
            } catch (\Exception $e) {
                return redirect()->back()->withErrors([
                    'path' => 'Error processing external file: '.$e->getMessage(),
                ]);
            }
        } else {
            // Local file - handle upload
            $uploadedFile = $request->file('file');
            $hash = hash_file('sha256', $uploadedFile->getRealPath());

            // Check if file with same hash already exists in this workspace
            $existingVersion = Version::where('hash', $hash)
                ->whereHas('file', fn ($q) => $q->where('workspace_id', $validated['workspace_id']))
                ->first();
            if ($existingVersion) {
                return redirect()->back()->withErrors([
                    'file' => 'This file already exists in this workspace.',
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
            'workspace_id' => $validated['workspace_id'],
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
            Placement::create([
                'file_id' => $file->id,
                'folder_id' => $validated['folder_id'],
                'order' => Placement::where('folder_id', $validated['folder_id'])->max('order') + 1,
            ]);
        }

        // Attach to multiple folders if specified (from files page)
        if (! empty($validated['folder_ids'])) {
            foreach ($validated['folder_ids'] as $folderId) {
                Placement::create([
                    'file_id' => $file->id,
                    'folder_id' => $folderId,
                    'order' => Placement::where('folder_id', $folderId)->max('order') + 1,
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
        // Check workspace access
        if ($file->workspace_id && ! $this->hasWorkspaceAccess(Auth::user(), $file->workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

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
                'locked' => 'This file is locked and cannot be modified.',
            ]);
        }

        $validated = $request->validated();

        // Update basic info first
        $file->updated_by = Auth::id();

        if (isset($validated['name'])) {
            $file->name = $validated['name'];
        }

        if (array_key_exists('description', $validated)) {
            $file->description = $validated['description'];
        }

        // Save name and description changes immediately
        $file->save();

        // Handle file replacement
        $hasNewVersion = false;

        if (isset($validated['path']) && ! empty($validated['path'])) {
            // External file replacement
            $disk = 'external';
            // External file - fetch metadata without storing locally
            $path = $validated['path'];

            try {
                // Download file using Laravel HTTP client
                $response = \Illuminate\Support\Facades\Http::timeout(60)
                    ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                    ->get($path);

                if (! $response->successful()) {
                    return redirect()->back()->withErrors([
                        'path' => 'Unable to download the external file (HTTP '.$response->status().').',
                    ]);
                }

                // Save to temporary file for hash calculation
                $tempFile = tempnam(sys_get_temp_dir(), 'ext_file_');
                file_put_contents($tempFile, $response->body());

                // Calculate hash and size
                $hash = hash_file('sha256', $tempFile);
                $size = filesize($tempFile);

                if ($file->hash === $hash) {
                    unlink($tempFile);

                    return redirect()->back()->withErrors([
                        'file' => 'This URL is identical to the current version.',
                    ]);
                }

                // Get MIME type from downloaded content
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $type = finfo_file($finfo, $tempFile) ?: 'application/octet-stream';
                finfo_close($finfo);

                // Clean up temp file
                unlink($tempFile);

                // Get extension from URL or MIME type
                $extension = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (! $extension) {
                    // Fallback: derive from MIME type
                    $mimeToExt = [
                        'application/pdf' => 'pdf',
                        'application/msword' => 'doc',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                        'application/vnd.ms-excel' => 'xls',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                    ];
                    $extension = $mimeToExt[$type] ?? 'bin';
                }
            } catch (\Exception $e) {
                return redirect()->back()->withErrors([
                    'path' => 'Error processing external file: '.$e->getMessage(),
                ]);
            }

            $hasNewVersion = true;
        } elseif ($request->hasFile('file')) {
            // Local file replacement
            $disk = 'local';
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

            // Clear the cached version relationship so it reloads fresh
            $file->unsetRelation('version');
            $file->unsetRelation('versions');

            // Update file type and extension
            $file->type = $type;
            $file->extension = $extension;
            $file->save();
        }

        // Handle folder assignments update
        if (array_key_exists('folder_ids', $validated)) {
            // Remove all existing placements
            $file->placements()->delete();

            // Add new placements
            if (! empty($validated['folder_ids'])) {
                foreach ($validated['folder_ids'] as $folderId) {
                    Placement::create([
                        'file_id' => $file->id,
                        'folder_id' => $folderId,
                        'order' => Placement::where('folder_id', $folderId)->max('order') + 1,
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'File updated successfully.');
    }

    /**
     * Remove the specified file.
     * If already trashed, force delete with password confirmation.
     * Otherwise, soft delete.
     */
    public function destroy(DestroyFileRequest $request, File $file): RedirectResponse
    {
        if ($file->locked) {
            return redirect()->back()->withErrors([
                'locked' => 'This file is locked and cannot be deleted.',
            ]);
        }

        if ($file->trashed()) {
            $file->forceDelete();

            return redirect()->back()->with('success', 'File permanently deleted.');
        }

        $file->delete();

        return redirect()->back()->with('success', 'File deleted successfully.');
    }

    /**
     * Restore a soft-deleted file.
     */
    public function restore(string $id): RedirectResponse
    {
        $file = File::withTrashed()->findOrFail($id);

        // Check workspace access
        if ($file->workspace_id && ! $this->hasWorkspaceAccess(Auth::user(), $file->workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        if (! $file->trashed()) {
            abort(404, 'This file is not deleted.');
        }

        $file->restore();
        $file->deleted_by = null;
        $file->save();

        return redirect()->back()->with('success', 'File restored successfully.');
    }

    /**
     * Download the specified file.
     */
    public function download(File $file): StreamedResponse|RedirectResponse
    {
        // Check workspace access
        if ($file->workspace_id && ! $this->hasWorkspaceAccess(Auth::user(), $file->workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $version = $file->version;

        if (! $version) {
            abort(404, 'File version not found.');
        }

        if ($version->disk === 'external') {
            // Verify external URL is reachable before redirecting
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)->head($version->path);

                if (! $response->successful()) {
                    abort(404, 'External file is not accessible (HTTP '.$response->status().').');
                }
            } catch (\Exception $e) {
                abort(404, 'External file is not reachable.');
            }

            // Increment download count only after verification
            $version->increment('downloads');

            // Redirect to external URL
            return redirect()->away($version->path);
        }

        // Increment download count for local files
        $version->increment('downloads');

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
