<?php

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'user']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    // Membership for owner is automatically created by WorkspaceFactory

    $this->actingAs($this->user);
});

it('can accept workspace invitation', function () {
    $member = User::factory()->create();

    // Create membership without joined_at (invitation pending)
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $member->id,
        'role' => 'member',
        'permissions' => ['files' => true, 'folders' => true],
        'joined_at' => null,
    ]);

    $this->actingAs($member);

    $response = $this->post(route('workspace.membership.accept', $this->workspace));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Invitation accepted successfully.');

    // Verify joined_at was set
    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $member->id,
        'joined_at' => now()->format('Y-m-d H:i:s'),
    ]);
});

it('cannot accept invitation if not a member', function () {
    $nonMember = User::factory()->create();
    $this->actingAs($nonMember);

    $response = $this->post(route('workspace.membership.accept', $this->workspace));

    $response->assertRedirect();
    $response->assertSessionHasErrors(['error' => 'You are not a member of this workspace.']);
});

it('can decline workspace invitation', function () {
    $member = User::factory()->create();

    // Create membership
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $member->id,
        'role' => 'member',
        'permissions' => ['files' => true, 'folders' => true],
        'joined_at' => now(),
    ]);

    $this->actingAs($member);

    $response = $this->post(route('workspace.membership.decline', $this->workspace));

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Invitation declined.');

    // Verify membership was removed
    $this->assertDatabaseMissing('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $member->id,
    ]);
});

it('can leave workspace voluntarily', function () {
    $member = User::factory()->create();

    // Create membership
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $member->id,
        'role' => 'member',
        'permissions' => ['files' => true, 'folders' => true],
        'joined_at' => now(),
    ]);

    $this->actingAs($member);

    $response = $this->post(route('workspace.membership.leave', $this->workspace));

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'You have left the workspace.');

    // Verify membership was removed
    $this->assertDatabaseMissing('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $member->id,
    ]);
});

it('cannot leave workspace if owner', function () {
    $response = $this->post(route('workspace.membership.leave', $this->workspace));

    $response->assertRedirect();
    $response->assertSessionHasErrors(['error' => 'Workspace owner cannot leave the workspace.']);

    // Verify membership still exists
    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

it('cannot leave workspace if not a member', function () {
    $nonMember = User::factory()->create();
    $this->actingAs($nonMember);

    $response = $this->post(route('workspace.membership.leave', $this->workspace));

    $response->assertRedirect();
    $response->assertSessionHasErrors(['error' => 'You are not a member of this workspace.']);
});

it('requires authentication for all membership actions', function () {
    Auth::logout();

    $response = $this->post(route('workspace.membership.accept', $this->workspace));
    $response->assertRedirect(route('login'));

    $response = $this->post(route('workspace.membership.decline', $this->workspace));
    $response->assertRedirect(route('login'));

    $response = $this->post(route('workspace.membership.leave', $this->workspace));
    $response->assertRedirect(route('login'));
});
