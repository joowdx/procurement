<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class UpdateWorkspace
{
    /**
     * Update group attributes.
     */
    public function handle(Workspace $workspace, array $data, User $user): Workspace
    {
        $workspace->updated_by = $user->id;

        if (isset($data['name'])) {
            $workspace->name = $data['name'];
            $workspace->slug = Str::slug($data['name']);
        }

        if (isset($data['slug'])) {
            $workspace->slug = $data['slug'];
        }

        if (array_key_exists('description', $data)) {
            $workspace->description = $data['description'] ?: null;
        }

        if (array_key_exists('settings', $data)) {
            $workspace->settings = $data['settings'];
        }

        if (array_key_exists('active', $data)) {
            $workspace->active = $data['active'];
        }

        $workspace->save();

        return $workspace;
    }
}
