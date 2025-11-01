<?php

use App\Actions\Workspaces\DelegateWorkspaceOwnership;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->newOwner = User::factory()->create(['role' => 'user']);
    $this->action = new DelegateWorkspaceOwnership;
});

it('delegates group ownership to new user', function () {
    $updatedGroup = $this->action->handle($this->workspace, $this->newOwner);

    expect($updatedGroup->user_id)->toBe($this->newOwner->id);
    $this->assertDatabaseHas('workspaces', [
        'id' => $this->workspace->id,
        'user_id' => $this->newOwner->id,
    ]);
});

it('creates membership for new owner with all permissions', function () {
    $this->action->handle($this->workspace, $this->newOwner);

    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->newOwner->id,
        'permissions' => json_encode([
            'users' => true,
            'files' => true,
            'folders' => true,
            'settings' => true,
        ]),
    ]);
});

it('updates old owner membership with all permissions', function () {
    // Update existing owner membership (created by WorkspaceFactory)
    Membership::where('workspace_id', $this->workspace->id)
        ->where('user_id', $this->owner->id)
        ->update([
            'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        ]);

    $this->action->handle($this->workspace, $this->newOwner);

    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'permissions' => json_encode([
            'users' => true,
            'files' => true,
            'folders' => true,
            'settings' => true,
        ]),
    ]);
});

it('creates membership for old owner if it does not exist', function () {
    $this->action->handle($this->workspace, $this->newOwner);

    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
    ]);
});
