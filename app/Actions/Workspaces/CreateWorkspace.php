<?php

namespace App\Actions\Workspaces;

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class CreateWorkspace
{
    /**
     * Create a new workspace and set up owner membership.
     */
    public function handle(array $data, User $owner): Workspace
    {
        // Generate slug from name if not provided
        $slug = $data['slug'] ?? Str::slug($data['name']);

        // Create workspace
        $workspace = Workspace::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'settings' => $data['settings'] ?? null,
            'active' => $data['active'] ?? true,
            'user_id' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        // Create owner membership with all permissions
        Membership::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'permissions' => [
                'users' => true,
                'files' => true,
                'folders' => true,
                'settings' => true,
            ],
            'joined_at' => now(),
        ]);

        return $workspace;
    }
}
