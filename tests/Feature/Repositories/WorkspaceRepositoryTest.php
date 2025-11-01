<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\WorkspaceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new WorkspaceRepository;
    $this->user = User::factory()->create(['role' => 'user']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->root = User::factory()->create(['role' => 'root']);
});

it('gets workspaces owned by user', function () {
    $workspace1 = Workspace::factory()->create(['user_id' => $this->user->id]);
    $workspace2 = Workspace::factory()->create(['user_id' => $this->user->id]);
    Workspace::factory()->create(['user_id' => $this->admin->id]); // Other user's workspace

    $workspaces = $this->repository->getUserWorkspaces($this->user);

    expect($workspaces)->toHaveCount(2);
    expect($workspaces->pluck('id')->toArray())->toContain($workspace1->id, $workspace2->id);
});

it('gets workspaces where user is a member', function () {
    $workspace1 = Workspace::factory()->create(['user_id' => $this->admin->id]);
    $workspace2 = Workspace::factory()->create(['user_id' => $this->admin->id]);

    // Create memberships
    Membership::create([
        'workspace_id' => $workspace1->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $workspaces = $this->repository->getUserWorkspaces($this->user);

    expect($workspaces)->toHaveCount(1);
    expect($workspaces->first()->id)->toBe($workspace1->id);
});

it('gets all workspaces for root users', function () {
    Workspace::factory()->create(['user_id' => $this->admin->id]);
    Workspace::factory()->create(['user_id' => $this->user->id]);

    $workspaces = $this->repository->getUserWorkspaces($this->root);

    expect($workspaces)->toHaveCount(2);
});

it('gets workspace with members', function () {
    $workspace = Workspace::factory()->create(['user_id' => $this->admin->id]);

    // Create memberships
    Membership::create([
        'workspace_id' => $workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $workspaceWithMembers = $this->repository->getWorkspaceWithMembers($workspace);

    expect($workspaceWithMembers->users)->toHaveCount(2); // Owner + member
    expect($workspaceWithMembers->users->pluck('id'))->toContain($this->user->id);
    expect($workspaceWithMembers->users->pluck('id'))->toContain($this->admin->id);
});

it('gets workspace statistics', function () {
    $workspace = Workspace::factory()->create(['user_id' => $this->admin->id]);

    // Owner membership automatically created by WorkspaceFactory

    // Create some folders and files for the workspace
    Folder::factory()->create(['workspace_id' => $workspace->id]);
    Folder::factory()->create(['workspace_id' => $workspace->id]);
    File::factory()->create(['workspace_id' => $workspace->id]);

    $stats = $this->repository->getWorkspaceStats($workspace);

    expect($stats)->toHaveKeys(['member_count', 'folder_count', 'file_count', 'active_folders', 'active_files', 'total_size']);
    expect($stats['folder_count'])->toBe(2);
    expect($stats['file_count'])->toBe(1);
    expect($stats['member_count'])->toBe(1); // Only the owner
});
