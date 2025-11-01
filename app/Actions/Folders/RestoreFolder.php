<?php

namespace App\Actions\Folders;

use App\Models\Folder;

class RestoreFolder
{
    /**
     * Restore soft-deleted folder.
     */
    public function handle(string $id): ?Folder
    {
        $folder = Folder::withTrashed()->find($id);

        if (! $folder) {
            return null;
        }

        $folder->restore();
        $folder->deleted_by = null;
        $folder->save();

        return $folder;
    }
}
