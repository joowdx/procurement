<?php

namespace App\Actions\Files;

use App\Models\File;

class RestoreFile
{
    /**
     * Restore soft-deleted file.
     */
    public function handle(string $id): ?File
    {
        $file = File::withTrashed()->find($id);

        if (! $file) {
            return null;
        }

        $file->restore();
        $file->deleted_by = null;
        $file->save();

        return $file;
    }
}
