<?php

use App\Actions\Workspaces\RemoveWorkspaceMember;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->member = User::factory()->create();
    $this->action = new RemoveWorkspaceMember;
});

it('removes user from group', function () {
    // Create membership first
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $result = $this->action->handle($this->workspace, $this->member);

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member->id,
    ]);
});

it('returns true even if membership does not exist', function () {
    $result = $this->action->handle($this->workspace, $this->member);

    expect($result)->toBeTrue();
});
