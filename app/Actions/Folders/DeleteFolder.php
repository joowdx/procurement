<?php

namespace App\Actions\Folders;

use App\Models\Folder;
use App\Models\User;

class DeleteFolder
{
    /**
     * Delete folder (soft or force).
     */
    public function handle(Folder $folder, bool $force = false, ?User $user = null): bool
    {
        if ($force) {
            return $folder->forceDelete();
        }

        // Soft delete
        if ($user) {
            $folder->deleted_by = $user->id;
            $folder->save();
        }

        return $folder->delete();
    }
}
