<?php

namespace App\Actions\Folders;

use App\Models\Folder;
use App\Models\User;

class CreateFolder
{
    /**
     * Create a new folder.
     */
    public function handle(array $data, User $user): Folder
    {
        $parentId = $data['parent_id'] ?? null;
        $parent = $parentId ? Folder::find($parentId) : null;

        $level = $parent ? $parent->level + 1 : 0;
        $route = $parent ? $parent->route.'/'.$data['name'] : $data['name'];

        return Folder::create([
            'workspace_id' => $data['workspace_id'],
            'parent_id' => $parentId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'route' => $route,
            'level' => $level,
            'order' => Folder::where('parent_id', $parentId)
                ->where('workspace_id', $data['workspace_id'])
                ->max('order') + 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
