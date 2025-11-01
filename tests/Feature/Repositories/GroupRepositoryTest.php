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

it('gets groups owned by user', function () {
    $group1 = Workspace::factory()->create(['user_id' => $this->user->id]);
    $group2 = Workspace::factory()->create(['user_id' => $this->user->id]);
    Workspace::factory()->create(['user_id' => $this->admin->id]); // Other user's group

    $groups = $this->repository->getUserGroups($this->user);

    expect($groups)->toHaveCount(2);
    expect($groups->pluck('id')->toArray())->toContain($group1->id, $group2->id);
});

it('gets groups where user is a member', function () {
    $group1 = Workspace::factory()->create(['user_id' => $this->admin->id]);
    $group2 = Workspace::factory()->create(['user_id' => $this->admin->id]);

    // Create memberships
    Membership::create([
        'workspace_id' => $group1->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $groups = $this->repository->getUserGroups($this->user);

    expect($groups)->toHaveCount(1);
    expect($groups->first()->id)->toBe($group1->id);
});

it('gets all groups for root users', function () {
    Workspace::factory()->create(['user_id' => $this->admin->id]);
    Workspace::factory()->create(['user_id' => $this->user->id]);

    $groups = $this->repository->getUserGroups($this->root);

    expect($groups)->toHaveCount(2);
});

it('gets group with members', function () {
    $group = Workspace::factory()->create(['user_id' => $this->admin->id]);

    // Create memberships
    Membership::create([
        'workspace_id' => $group->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $groupWithMembers = $this->repository->getGroupWithMembers($group);

    expect($groupWithMembers->users)->toHaveCount(2); // Owner + member
    expect($groupWithMembers->users->pluck('id'))->toContain($this->user->id);
    expect($groupWithMembers->users->pluck('id'))->toContain($this->admin->id);
});

it('gets group statistics', function () {
    $group = Workspace::factory()->create(['user_id' => $this->admin->id]);

    // Owner membership automatically created by WorkspaceFactory

    // Create some folders and files for the group
    Folder::factory()->create(['workspace_id' => $group->id]);
    Folder::factory()->create(['workspace_id' => $group->id]);
    File::factory()->create(['workspace_id' => $group->id]);

    $stats = $this->repository->getGroupStats($group);

    expect($stats)->toHaveKeys(['member_count', 'folder_count', 'file_count', 'active_folders', 'active_files', 'total_size']);
    expect($stats['folder_count'])->toBe(2);
    expect($stats['file_count'])->toBe(1);
    expect($stats['member_count'])->toBe(1); // Only the owner
});
