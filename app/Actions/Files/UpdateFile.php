<?php

namespace App\Actions\Files;

use App\Models\File;
use App\Models\User;
use App\Models\Version;
use Illuminate\Http\UploadedFile;

class UpdateFile
{
    /**
     * Update file metadata and/or create new version.
     */
    public function handle(File $file, array $data, User $user): File
    {
        $file->updated_by = $user->id;

        // Update basic info
        if (isset($data['name'])) {
            $file->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $file->description = $data['description'] ?: null;
        }

        $file->save();

        // Handle file replacement
        if (isset($data['path']) || isset($data['file'])) {
            $this->createNewVersion($file, $data, $user);
        }

        return $file;
    }

    /**
     * Create new file version.
     */
    private function createNewVersion(File $file, array $data, User $user): void
    {
        if (isset($data['path'])) {
            // External file
            $result = $this->handleExternalFile($data['path'], $file->group_id);
            $disk = 'external';
        } else {
            // Local file
            $result = $this->handleLocalFile($data['file'], $file->group_id);
            $disk = 'local';
        }

        // Check if hash is different
        if ($file->hash === $result['hash']) {
            throw new \Exception('File is identical to current version.');
        }

        // Create new version
        $lastVersion = $file->versions()->orderBy('number', 'desc')->first();
        Version::create([
            'file_id' => $file->id,
            'number' => $lastVersion->number + 1,
            'hash' => $result['hash'],
            'disk' => $disk,
            'path' => $result['path'],
            'size' => $result['size'],
            'created_by' => $user->id,
        ]);

        // Update file type and extension
        $file->type = $result['type'];
        $file->extension = $result['extension'];
        $file->save();

        // Clear cached relationships
        $file->unsetRelation('version');
        $file->unsetRelation('versions');
    }

    /**
     * Handle external file.
     */
    private function handleExternalFile(string $path, string $groupId): array
    {
        // Similar to UploadFile::handleExternalFile but simplified
        $tempFile = tempnam(sys_get_temp_dir(), 'ext_file_');

        $ch = curl_init($path);
        $fp = fopen($tempFile, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (! $result || $httpCode !== 200) {
            unlink($tempFile);
            throw new \Exception("Unable to download external file (HTTP {$httpCode}).");
        }

        $hash = hash_file('sha256', $tempFile);
        $size = filesize($tempFile);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $tempFile) ?: 'application/octet-stream';
        finfo_close($finfo);

        $extension = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin';

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
     * Handle local file.
     */
    private function handleLocalFile(UploadedFile $uploadedFile, string $groupId): array
    {
        $hash = hash_file('sha256', $uploadedFile->getRealPath());
        $hashPath = "groups/{$groupId}/".substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
        $path = $uploadedFile->storeAs($hashPath, $uploadedFile->getClientOriginalName(), 'local');

        return [
            'hash' => $hash,
            'path' => $path,
            'size' => $uploadedFile->getSize(),
            'type' => $uploadedFile->getMimeType(),
            'extension' => $uploadedFile->getClientOriginalExtension(),
        ];
    }
}
