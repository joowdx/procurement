<?php

namespace App\Actions\Files;

use App\Models\File;
use App\Models\Folder;
use App\Models\Placement;
use App\Models\User;
use App\Models\Version;
use Illuminate\Http\UploadedFile;

class UploadFile
{
    /**
     * Upload file and create version.
     */
    public function handle(array $data, User $user): File
    {
        $disk = $data['disk'];
        $workspaceId = $data['workspace_id'];

        // Handle based on disk type
        if ($disk === 'external') {
            $result = $this->handleExternalFile($data['path'], $workspaceId);
        } else {
            $result = $this->handleLocalFile($data['file'], $workspaceId);
        }

        // Create file record
        $file = File::create([
            'workspace_id' => $workspaceId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $result['type'],
            'extension' => $result['extension'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Create first version
        Version::create([
            'file_id' => $file->id,
            'number' => 1,
            'hash' => $result['hash'],
            'disk' => $disk,
            'path' => $result['path'],
            'size' => $result['size'],
            'created_by' => $user->id,
        ]);

        // Attach to folders
        $this->attachToFolders($file, $data);

        return $file;
    }

    /**
     * Handle external file upload.
     */
    private function handleExternalFile(string $path, string $workspaceId): array
    {
        // Download file using Laravel HTTP client
        $response = \Illuminate\Support\Facades\Http::timeout(60)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get($path);

        if (! $response->successful()) {
            throw new \Exception("Unable to download external file (HTTP {$response->status()}).");
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'ext_file_');
        file_put_contents($tempFile, $response->body());

        // Calculate hash and size
        $hash = hash_file('sha256', $tempFile);
        $size = filesize($tempFile);

        // Get MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $tempFile) ?: 'application/octet-stream';
        finfo_close($finfo);

        // Get extension
        $extension = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (! $extension) {
            $mimeToExt = [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            ];
            $extension = $mimeToExt[$type] ?? 'bin';
        }

        // Clean up temp file
        unlink($tempFile);

        return [
            'hash' => $hash,
            'path' => $path,
            'size' => $size,
            'type' => $type,
            'extension' => $extension,
        ];
    }

    /**
     * Handle local file upload.
     */
    private function handleLocalFile(UploadedFile $uploadedFile, string $workspaceId): array
    {
        $hash = hash_file('sha256', $uploadedFile->getRealPath());

        // Store with workspace-scoped path
        $hashPath = "workspaces/{$workspaceId}/".substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
        $path = $uploadedFile->storeAs($hashPath, $uploadedFile->getClientOriginalName(), 'local');

        return [
            'hash' => $hash,
            'path' => $path,
            'size' => $uploadedFile->getSize(),
            'type' => $uploadedFile->getMimeType(),
            'extension' => $uploadedFile->getClientOriginalExtension(),
        ];
    }

    /**
     * Attach file to folders.
     */
    private function attachToFolders(File $file, array $data): void
    {
        // Single folder (from folder page)
        if (isset($data['folder_id'])) {
            Placement::create([
                'file_id' => $file->id,
                'folder_id' => $data['folder_id'],
                'order' => Placement::where('folder_id', $data['folder_id'])->max('order') + 1,
            ]);
        }

        // Multiple folders (from files page)
        if (! empty($data['folder_ids'])) {
            foreach ($data['folder_ids'] as $folderId) {
                Placement::create([
                    'file_id' => $file->id,
                    'folder_id' => $folderId,
                    'order' => Placement::where('folder_id', $folderId)->max('order') + 1,
                ]);
            }
        }
    }
}
