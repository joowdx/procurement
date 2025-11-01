<?php

namespace App\Actions\Folders;

use App\Models\Folder;
use App\Models\User;

class UpdateFolder
{
    /**
     * Update folder attributes.
     */
    public function handle(Folder $folder, array $data, User $user): Folder
    {
        $folder->updated_by = $user->id;

        // If parent changes, update level
        if (isset($data['parent_id']) && $data['parent_id'] !== $folder->parent_id) {
            $parent = $data['parent_id'] ? Folder::find($data['parent_id']) : null;
            $folder->parent_id = $data['parent_id'];
            $folder->level = $parent ? $parent->level + 1 : 0;
        }

        if (isset($data['name'])) {
            $folder->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $folder->description = $data['description'] ?: null;
        }

        $folder->save();

        return $folder;
    }
}
