<?php

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new WorkspaceAuthorizationService;
    $this->user = User::factory()->create(['role' => 'user']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->root = User::factory()->create(['role' => 'root']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->admin->id]);
});

it('allows group owner to access group', function () {
    $result = $this->service->canAccessWorkspace($this->admin, $this->workspace);

    expect($result)->toBeTrue();
});

it('allows root users to access any group', function () {
    $result = $this->service->canAccessWorkspace($this->root, $this->workspace);

    expect($result)->toBeTrue();
});

it('allows group members to access group', function () {
    // Create membership
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $result = $this->service->canAccessWorkspace($this->user, $this->workspace);

    expect($result)->toBeTrue();
});

it('denies access to non-members', function () {
    $otherUser = User::factory()->create();

    $result = $this->service->canAccessWorkspace($otherUser, $this->workspace);

    expect($result)->toBeFalse();
});

it('checks specific permissions for members', function () {
    // Create membership with limited permissions
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    expect($this->service->hasPermission($this->user, $this->workspace, 'files'))->toBeTrue();
    expect($this->service->hasPermission($this->user, $this->workspace, 'users'))->toBeFalse();
});

it('allows group owner all permissions', function () {
    expect($this->service->hasPermission($this->admin, $this->workspace, 'users'))->toBeTrue();
    expect($this->service->hasPermission($this->admin, $this->workspace, 'files'))->toBeTrue();
    expect($this->service->hasPermission($this->admin, $this->workspace, 'folders'))->toBeTrue();
    expect($this->service->hasPermission($this->admin, $this->workspace, 'settings'))->toBeTrue();
});

it('allows root users all permissions', function () {
    expect($this->service->hasPermission($this->root, $this->workspace, 'users'))->toBeTrue();
    expect($this->service->hasPermission($this->root, $this->workspace, 'files'))->toBeTrue();
    expect($this->service->hasPermission($this->root, $this->workspace, 'folders'))->toBeTrue();
    expect($this->service->hasPermission($this->root, $this->workspace, 'settings'))->toBeTrue();
});

it('throws exception when access is denied', function () {
    $otherUser = User::factory()->create();

    expect(fn () => $this->service->ensureWorkspaceAccess($otherUser, $this->workspace))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('does not throw exception when access is allowed', function () {
    expect(fn () => $this->service->ensureWorkspaceAccess($this->admin, $this->workspace))
        ->not->toThrow(\Exception::class);
});

it('auto-creates membership for group owner', function () {
    $this->service->autoCreateOwnerMembership($this->workspace, $this->admin);

    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->admin->id,
        'role' => 'owner',
        'permissions' => json_encode([
            'users' => true,
            'files' => true,
            'folders' => true,
            'settings' => true,
        ]),
    ]);
});
