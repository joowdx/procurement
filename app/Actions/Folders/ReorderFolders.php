<?php

namespace App\Actions\Folders;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReorderFolders
{
    /**
     * Reorder folders.
     */
    public function handle(array $folders, User $user): bool
    {
        return DB::transaction(function () use ($folders, $user) {
            foreach ($folders as $folderData) {
                Folder::where('id', $folderData['id'])
                    ->update([
                        'order' => $folderData['order'],
                        'updated_by' => $user->id,
                    ]);
            }

            return true;
        });
    }
}
