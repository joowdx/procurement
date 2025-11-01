<?php

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    Session::put('current_workspace_id', $this->workspace->id);
    $this->actingAs($this->user);
});

describe('Store Method (Invite Member)', function () {
    it('owner can invite members', function () {
        $newMember = User::factory()->create();

        $data = [
            'user_id' => $newMember->id,
            'permissions' => [
                'users' => true,
                'files' => true,
                'folders' => true,
                'settings' => false,
            ],
        ];

        $response = $this->postJson(route('workspace.membership.store'), $data);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'data',
        ]);

        $this->assertDatabaseHas('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $newMember->id,
            'role' => 'member',
        ]);
    });

    it('members with users permission can invite', function () {
        $member = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'permissions' => ['users' => true],
            'joined_at' => now(),
        ]);

        $this->actingAs($member);

        $newMember = User::factory()->create();
        $data = ['user_id' => $newMember->id];

        $response = $this->postJson(route('workspace.membership.store'), $data);

        $response->assertSuccessful();

        $this->assertDatabaseHas('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $newMember->id,
        ]);
    });

    it('members without users permission cannot invite', function () {
        $member = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'permissions' => ['files' => true, 'folders' => true, 'settings' => true], // No users permission
            'joined_at' => now(),
        ]);

        $this->actingAs($member);

        $newMember = User::factory()->create();
        $data = ['user_id' => $newMember->id];

        $response = $this->postJson(route('workspace.membership.store'), $data);

        $response->assertForbidden();
    });

    it('cannot invite existing members', function () {
        $member = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'permissions' => ['files' => true],
            'joined_at' => now(),
        ]);

        $data = ['user_id' => $member->id];

        $response = $this->postJson(route('workspace.membership.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    });

    it('can set custom permissions', function () {
        $newMember = User::factory()->create();

        $data = [
            'user_id' => $newMember->id,
            'permissions' => [
                'users' => false,
                'files' => true,
                'folders' => false,
                'settings' => true,
            ],
        ];

        $response = $this->postJson(route('workspace.membership.store'), $data);

        $response->assertSuccessful();

        $this->assertDatabaseHas('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $newMember->id,
            'permissions' => json_encode([
                'users' => false,
                'files' => true,
                'folders' => false,
                'settings' => true,
            ]),
        ]);
    });

    it('validates user_id exists', function () {
        $data = ['user_id' => 'non-existent-id'];

        $response = $this->postJson(route('workspace.membership.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    });

    it('validates required fields', function () {
        $response = $this->postJson(route('workspace.membership.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    });

    it('validates permission structure', function () {
        $newMember = User::factory()->create();

        $data = [
            'user_id' => $newMember->id,
            'permissions' => 'invalid-permissions', // Should be array
        ];

        $response = $this->postJson(route('workspace.membership.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['permissions']);
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();
        // Make user a member without users permission
        \App\Models\Membership::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'permissions' => [
                'users' => false, // No users permission
                'files' => true,
                'folders' => true,
                'settings' => true,
            ],
            'joined_at' => now(),
        ]);
        Session::put('current_workspace_id', $otherWorkspace->id);

        $newMember = User::factory()->create();
        $data = ['user_id' => $newMember->id];

        $response = $this->postJson(route('workspace.membership.store'), $data);

        $response->assertForbidden();
    });

    it('root users can invite to any workspace', function () {
        $this->user->update(['role' => 'root']);
        $otherWorkspace = Workspace::factory()->create();
        Session::put('current_workspace_id', $otherWorkspace->id);

        $newMember = User::factory()->create();
        $data = ['user_id' => $newMember->id];

        $response = $this->postJson(route('workspace.membership.store'), $data);

        $response->assertSuccessful();
    });
});

describe('Update Method (Update Permissions)', function () {
    beforeEach(function () {
        $this->member = User::factory()->create();
        $this->membership = Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->member->id,
            'role' => 'member',
            'permissions' => ['files' => true, 'folders' => true],
            'joined_at' => now(),
        ]);
    });

    it('owner can update member permissions', function () {
        $data = [
            'permissions' => [
                'users' => true,
                'files' => false,
                'folders' => true,
                'settings' => true,
            ],
        ];

        $response = $this->putJson(route('workspace.membership.update', $this->membership), $data);

        $response->assertSuccessful();
        $response->assertJsonStructure(['message']);

        $this->assertDatabaseHas('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->member->id,
            'permissions' => json_encode([
                'users' => true,
                'files' => false,
                'folders' => true,
                'settings' => true,
            ]),
        ]);
    });

    it('members with users permission can update permissions', function () {
        $adminMember = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $adminMember->id,
            'role' => 'member',
            'permissions' => ['users' => true],
            'joined_at' => now(),
        ]);

        $this->actingAs($adminMember);

        $data = [
            'permissions' => [
                'users' => false,
                'files' => true,
                'folders' => true,
                'settings' => false,
            ],
        ];

        $response = $this->putJson(route('workspace.membership.update', $this->membership), $data);

        $response->assertSuccessful();
    });

    it('cannot update own permissions', function () {
        // Get the owner's membership
        $ownerMembership = Membership::where('workspace_id', $this->workspace->id)
            ->where('user_id', $this->user->id)
            ->first();

        $data = [
            'permissions' => [
                'users' => true,
                'files' => true,
                'folders' => true,
                'settings' => true,
            ],
        ];

        $response = $this->putJson(route('workspace.membership.update', $ownerMembership), $data);

        $response->assertStatus(500); // Throws exception for owner protection
    });

    it('cannot update owner permissions', function () {
        // Get owner membership to update
        $ownerMembership = Membership::where('workspace_id', $this->workspace->id)
            ->where('user_id', $this->user->id)
            ->first();

        $data = [
            'permissions' => [
                'users' => false,
                'files' => false,
                'folders' => false,
                'settings' => false,
            ],
        ];

        $response = $this->putJson(route('workspace.membership.update', $ownerMembership), $data);

        $response->assertStatus(500); // Throws exception for owner protection
    });

    it('validates permission structure', function () {
        $data = [
            'permissions' => 'invalid-permissions', // Should be array
        ];

        $response = $this->putJson(route('workspace.membership.update', $this->membership), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['permissions']);
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();
        // Make user a member of the other workspace but without users permission
        \App\Models\Membership::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'permissions' => [
                'users' => false, // No users permission
                'files' => true,
                'folders' => true,
                'settings' => true,
            ],
            'joined_at' => now(),
        ]);
        Session::put('current_workspace_id', $otherWorkspace->id);

        $data = [
            'permissions' => ['files' => true],
        ];

        $response = $this->putJson(route('workspace.membership.update', $this->membership), $data);

        $response->assertForbidden();
    });

    it('prevents updating permissions for non-existent members', function () {
        $nonMember = User::factory()->create();
        // Create a fake membership ID that doesn't exist
        $fakeMembershipId = '01HXXXXXXXXXXXXXXXXXXXXXXX';

        $data = [
            'permissions' => [
                'users' => false,
                'files' => true,
                'folders' => true,
                'settings' => false,
            ],
        ];

        $response = $this->putJson(route('workspace.membership.update', $fakeMembershipId), $data);

        $response->assertNotFound();
    });

    it('members without users permission cannot update permissions', function () {
        $regularMember = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $regularMember->id,
            'role' => 'member',
            'permissions' => ['files' => true, 'folders' => true, 'settings' => true], // No users permission
            'joined_at' => now(),
        ]);

        $this->actingAs($regularMember);

        $data = [
            'permissions' => [
                'users' => false,
                'files' => true,
                'folders' => true,
                'settings' => false,
            ],
        ];

        $response = $this->putJson(route('workspace.membership.update', $this->membership), $data);

        $response->assertForbidden();
    });
});

describe('Destroy Method (Remove Member)', function () {
    beforeEach(function () {
        $this->member = User::factory()->create();
        $this->membership = Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->member->id,
            'role' => 'member',
            'permissions' => ['files' => true],
            'joined_at' => now(),
        ]);
    });

    it('owner can remove members', function () {
        $response = $this->deleteJson(route('workspace.membership.destroy', $this->membership));

        $response->assertSuccessful();
        $response->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->member->id,
        ]);
    });

    it('members with users permission can remove members', function () {
        $adminMember = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $adminMember->id,
            'role' => 'member',
            'permissions' => ['users' => true],
            'joined_at' => now(),
        ]);

        $this->actingAs($adminMember);

        $response = $this->deleteJson(route('workspace.membership.destroy', $this->membership));

        $response->assertSuccessful();
    });

    it('cannot remove owner', function () {
        // Get owner membership
        $ownerMembership = Membership::where('workspace_id', $this->workspace->id)
            ->where('user_id', $this->user->id)
            ->first();

        $response = $this->deleteJson(route('workspace.membership.destroy', $ownerMembership));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user']);
    });

    it('cannot remove self', function () {
        $member = User::factory()->create();
        $memberMembership = Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'permissions' => ['users' => true],
            'joined_at' => now(),
        ]);

        $this->actingAs($member);

        $response = $this->deleteJson(route('workspace.membership.destroy', $memberMembership));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user']);
    });

    it('removes membership record', function () {
        $response = $this->deleteJson(route('workspace.membership.destroy', $this->membership));

        $response->assertSuccessful();

        $this->assertDatabaseMissing('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->member->id,
        ]);
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();
        // Make user a member of the other workspace but without users permission
        \App\Models\Membership::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'permissions' => [
                'users' => false, // No users permission
                'files' => true,
                'folders' => true,
                'settings' => true,
            ],
            'joined_at' => now(),
        ]);
        Session::put('current_workspace_id', $otherWorkspace->id);

        $response = $this->deleteJson(route('workspace.membership.destroy', $this->membership));

        $response->assertForbidden();
    });

    it('prevents removing non-existent members', function () {
        $nonMember = User::factory()->create();
        // Create a fake membership ID that doesn't exist
        $fakeMembershipId = '01HXXXXXXXXXXXXXXXXXXXXXXX';

        $response = $this->deleteJson(route('workspace.membership.destroy', $fakeMembershipId));

        $response->assertNotFound();
    });

    it('members without users permission cannot remove members', function () {
        $regularMember = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $regularMember->id,
            'role' => 'member',
            'permissions' => ['files' => true, 'folders' => true, 'settings' => true], // No users permission
            'joined_at' => now(),
        ]);

        $this->actingAs($regularMember);

        $response = $this->deleteJson(route('workspace.membership.destroy', $this->membership));

        $response->assertForbidden();
    });
});

describe('Authorization Edge Cases', function () {
    it('root users can manage members in any workspace', function () {
        $this->user->update(['role' => 'root']);
        $otherWorkspace = Workspace::factory()->create();
        Session::put('current_workspace_id', $otherWorkspace->id);

        $member = User::factory()->create();

        // Test invite
        $response = $this->postJson(route('workspace.membership.store'), ['user_id' => $member->id]);
        $response->assertSuccessful();

        // Get the created membership
        $membership = Membership::where('workspace_id', $otherWorkspace->id)
            ->where('user_id', $member->id)
            ->first();

        // Test update permissions
        $response = $this->putJson(route('workspace.membership.update', $membership), [
            'permissions' => ['files' => true],
        ]);
        $response->assertSuccessful();

        // Test remove
        $response = $this->deleteJson(route('workspace.membership.destroy', $membership));
        $response->assertSuccessful();
    });

    it('workspace owner always has access regardless of membership', function () {
        // Remove membership record (simulating edge case)
        Membership::where('workspace_id', $this->workspace->id)
            ->where('user_id', $this->user->id)
            ->delete();

        $member = User::factory()->create();

        // Owner should still be able to invite
        $response = $this->postJson(route('workspace.membership.store'), ['user_id' => $member->id]);
        $response->assertSuccessful();
    });

    it('validates workspace exists', function () {
        // Delete the user's workspace so they have none
        $this->workspace->delete();
        Session::put('current_workspace_id', 'non-existent-workspace');

        $member = User::factory()->create();

        $response = $this->postJson(route('workspace.membership.store'), ['user_id' => $member->id]);

        $response->assertNotFound();
    });

    it('handles concurrent membership updates', function () {
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        // Add both members
        $membership1 = Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member1->id,
            'role' => 'member',
            'permissions' => ['files' => true],
            'joined_at' => now(),
        ]);

        $membership2 = Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member2->id,
            'role' => 'member',
            'permissions' => ['files' => true],
            'joined_at' => now(),
        ]);

        // Update both members' permissions
        $response1 = $this->putJson(route('workspace.membership.update', $membership1), [
            'permissions' => ['files' => false, 'folders' => true],
        ]);

        $response2 = $this->putJson(route('workspace.membership.update', $membership2), [
            'permissions' => ['files' => true, 'settings' => true],
        ]);

        $response1->assertSuccessful();
        $response2->assertSuccessful();

        // Verify both updates were applied
        $this->assertDatabaseHas('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $member1->id,
            'permissions' => json_encode(['files' => false, 'folders' => true]),
        ]);

        $this->assertDatabaseHas('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $member2->id,
            'permissions' => json_encode(['files' => true, 'settings' => true]),
        ]);
    });
});
