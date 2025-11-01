<?php

use App\Actions\Workspaces\AddWorkspaceMember;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->newUser = User::factory()->create();
    $this->action = new AddWorkspaceMember;
});

it('adds user to group with default permissions', function () {
    $permissions = [
        'users' => false,
        'files' => true,
        'folders' => true,
        'settings' => true,
    ];

    $membership = $this->action->handle($this->workspace, $this->newUser, $permissions);

    expect($membership)->toBeInstanceOf(Membership::class);
    expect($membership->workspace_id)->toBe($this->workspace->id);
    expect($membership->user_id)->toBe($this->newUser->id);
    expect($membership->role)->toBe('member');
    expect($membership->permissions)->toBe($permissions);
});

it('creates membership record in database', function () {
    $permissions = [
        'users' => true,
        'files' => true,
        'folders' => true,
        'settings' => true,
    ];

    $this->action->handle($this->workspace, $this->newUser, $permissions);

    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->newUser->id,
        'role' => 'member',
        'permissions' => json_encode($permissions),
    ]);
});

it('handles minimal permissions', function () {
    $permissions = [
        'users' => false,
    ];

    $membership = $this->action->handle($this->workspace, $this->newUser, $permissions);

    expect($membership->permissions)->toBe([
        'users' => false,
        'files' => true,
        'folders' => true,
        'settings' => true,
    ]);
});
