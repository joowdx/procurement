<?php

use App\Actions\Workspaces\UpdateMemberPermissions;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->member = User::factory()->create();
    $this->action = new UpdateMemberPermissions;
});

it('updates member permissions', function () {
    // Create membership first
    $membership = Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $newPermissions = [
        'users' => true,
        'files' => false,
        'folders' => true,
        'settings' => false,
    ];

    $updatedMembership = $this->action->handle($this->workspace, $this->member, $newPermissions);

    expect($updatedMembership->permissions)->toBe($newPermissions);
    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member->id,
        'permissions' => json_encode($newPermissions),
    ]);
});

it('creates membership if it does not exist', function () {
    $permissions = [
        'users' => true,
        'files' => true,
        'folders' => true,
        'settings' => true,
    ];

    $membership = $this->action->handle($this->workspace, $this->member, $permissions);

    expect($membership)->toBeInstanceOf(Membership::class);
    expect($membership->permissions)->toBe($permissions);
    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member->id,
    ]);
});
