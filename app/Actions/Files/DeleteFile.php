<?php

namespace App\Actions\Files;

use App\Models\File;
use App\Models\User;

class DeleteFile
{
    /**
     * Delete file (soft or force).
     */
    public function handle(File $file, bool $force = false, ?User $user = null): bool
    {
        if ($force) {
            return $file->forceDelete();
        }

        // Soft delete
        if ($user) {
            $file->deleted_by = $user->id;
            $file->save();
        }

        return $file->delete();
    }
}
