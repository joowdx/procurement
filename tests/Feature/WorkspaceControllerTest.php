<?php

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user);
});

describe('Store Method', function () {
    it('admin can create workspace', function () {
        $data = [
            'name' => 'Test Workspace',
            'description' => 'Test Description',
        ];

        $response = $this->post(route('workspaces.store'), $data);

        $response->assertRedirect(route('workspace.edit'));
        $response->assertSessionHas('success', 'Workspace created successfully.');

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Test Workspace',
            'description' => 'Test Description',
            'user_id' => $this->user->id,
        ]);

        // Check that workspace is set as current workspace in session
        expect(Session::get('current_workspace_id'))->not->toBeNull();
    });

    it('standard user cannot create workspace', function () {
        $this->user->update(['role' => 'user']);

        $data = [
            'name' => 'Test Workspace',
            'description' => 'Test Description',
        ];

        $response = $this->post(route('workspaces.store'), $data);

        $response->assertForbidden();
    });

    it('creates membership automatically', function () {
        $data = [
            'name' => 'Test Workspace',
            'description' => 'Test Description',
        ];

        $response = $this->post(route('workspaces.store'), $data);

        $response->assertRedirect();

        $workspace = Workspace::where('name', 'Test Workspace')->first();
        $this->assertDatabaseHas('memberships', [
            'workspace_id' => $workspace->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
    });

    it('sets workspace as current in session', function () {
        $data = [
            'name' => 'Test Workspace',
            'description' => 'Test Description',
        ];

        $response = $this->post(route('workspaces.store'), $data);

        $response->assertRedirect();

        $workspace = Workspace::where('name', 'Test Workspace')->first();
        expect(Session::get('current_workspace_id'))->toBe($workspace->id);
    });

    it('generates unique slug', function () {
        $data = [
            'name' => 'Test Workspace',
            'description' => 'Test Description',
        ];

        $response = $this->post(route('workspaces.store'), $data);

        $response->assertRedirect();

        $workspace = Workspace::where('name', 'Test Workspace')->first();
        expect($workspace->slug)->toBe('test-workspace');
    });

    it('validates required fields', function () {
        $response = $this->post(route('workspaces.store'), []);

        $response->assertSessionHasErrors(['name']);
    });

    it('validates slug uniqueness', function () {
        Workspace::factory()->create(['slug' => 'existing-workspace']);

        $data = [
            'name' => 'Existing Workspace',
            'description' => 'Test Description',
        ];

        $response = $this->post(route('workspaces.store'), $data);

        $response->assertSessionHasErrors(['name']);
    });
});

describe('Select Method', function () {
    it('can select workspace', function () {
        $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson(route('workspaces.select', $workspace));

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'workspace' => [
                'id',
                'name',
                'slug',
                'description',
                'user_id',
                'created_at',
                'updated_at',
            ],
        ]);

        // Check that workspace is set as current workspace in session
        expect(Session::get('current_workspace_id'))->toBe($workspace->id);
    });

    it('updates session correctly', function () {
        $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson(route('workspaces.select', $workspace));

        $response->assertSuccessful();
        expect(Session::get('current_workspace_id'))->toBe($workspace->id);
    });

    it('returns JSON response', function () {
        $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson(route('workspaces.select', $workspace));

        $response->assertSuccessful();
        $response->assertJsonStructure(['message', 'workspace']);
    });

    it('only workspace members can select', function () {
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->post(route('workspaces.select', $workspace));

        $response->assertForbidden();
    });

    it('allows root users to select any workspace', function () {
        $this->user->update(['role' => 'root']);
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->postJson(route('workspaces.select', $workspace));

        $response->assertSuccessful();
        expect(Session::get('current_workspace_id'))->toBe($workspace->id);
    });
});

describe('Edit Method', function () {
    beforeEach(function () {
        $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
        Session::put('current_workspace_id', $this->workspace->id);
    });

    it('returns correct component using Inertia', function () {
        $response = $this->get(route('workspace.edit'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('workspaces/edit')
            ->has('workspace', fn ($workspace) => $workspace
                ->where('id', $this->workspace->id)
                ->where('name', $this->workspace->name)
                ->where('slug', $this->workspace->slug)
                ->has('members')
                ->etc()
            )
            ->has('stats')
        );
    });

    it('returns workspace data', function () {
        $response = $this->get(route('workspace.edit'));

        $response->assertInertia(fn ($page) => $page
            ->component('workspaces/edit')
            ->has('workspace', fn ($workspace) => $workspace
                ->where('id', $this->workspace->id)
                ->where('name', $this->workspace->name)
                ->where('description', $this->workspace->description)
                ->where('user_id', $this->user->id)
                ->etc()
            )
        );
    });

    it('returns members list', function () {
        $member = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'permissions' => ['files' => true, 'folders' => true],
            'joined_at' => now(),
        ]);

        $response = $this->get(route('workspace.edit'));

        $response->assertInertia(fn ($page) => $page
            ->component('workspaces/edit')
            ->has('workspace.members', 2) // Owner + member
        );
    });

    it('requires workspace access with settings permission', function () {
        // Create workspace with this user as member but without settings permission
        $otherWorkspace = Workspace::factory()->create();
        \App\Models\Membership::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'permissions' => [
                'users' => false,
                'files' => true,
                'folders' => true,
                'settings' => false, // No settings permission
            ],
            'joined_at' => now(),
        ]);
        Session::put('current_workspace_id', $otherWorkspace->id);

        $response = $this->get(route('workspace.edit'));

        $response->assertForbidden();
    });

    it('shows invitation page when user has no workspaces', function () {
        // Create a user with no workspaces
        $userWithoutWorkspaces = User::factory()->create(['role' => 'user']);
        $this->actingAs($userWithoutWorkspaces);

        $response = $this->get(route('workspace.edit'));

        $response->assertInertia(fn ($page) => $page->component('invitation'));
    });
});

describe('Update Method', function () {
    beforeEach(function () {
        $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
        Session::put('current_workspace_id', $this->workspace->id);
    });

    it('can update workspace name', function () {
        $data = [
            'name' => 'Updated Workspace Name',
        ];

        $response = $this->put(route('workspace.update'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Workspace updated successfully.');

        $this->assertDatabaseHas('workspaces', [
            'id' => $this->workspace->id,
            'name' => 'Updated Workspace Name',
        ]);
    });

    it('can update workspace description', function () {
        $data = [
            'description' => 'Updated Description',
        ];

        $response = $this->put(route('workspace.update'), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('workspaces', [
            'id' => $this->workspace->id,
            'description' => 'Updated Description',
        ]);
    });

    it('validates slug uniqueness', function () {
        $otherWorkspace = Workspace::factory()->create(['slug' => 'existing-slug']);

        $data = [
            'name' => 'Existing Slug',
        ];

        $response = $this->put(route('workspace.update'), $data);

        $response->assertSessionHasErrors(['name']);
    });

    it('requires workspace access with settings permission', function () {
        $otherWorkspace = Workspace::factory()->create();
        // Make user a member but without settings permission
        \App\Models\Membership::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'permissions' => [
                'users' => false,
                'files' => true,
                'folders' => true,
                'settings' => false, // No settings permission
            ],
            'joined_at' => now(),
        ]);
        Session::put('current_workspace_id', $otherWorkspace->id);

        $response = $this->put(route('workspace.update'), [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertForbidden();
    });

    it('owner can update', function () {
        $data = [
            'name' => 'Owner Update',
        ];

        $response = $this->put(route('workspace.update'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Workspace updated successfully.');
    });

    it('members with settings permission can update', function () {
        $member = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'permissions' => ['settings' => true],
            'joined_at' => now(),
        ]);

        $this->actingAs($member);

        $data = [
            'name' => 'Member Update',
        ];

        $response = $this->put(route('workspace.update'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Workspace updated successfully.');
    });

    it('members without settings permission cannot update', function () {
        $member = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'permissions' => ['settings' => false],
            'joined_at' => now(),
        ]);

        $this->actingAs($member);

        $response = $this->put(route('workspace.update'), [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertForbidden();
    });

    it('can delegate workspace ownership', function () {
        $newOwner = User::factory()->create();

        $data = [
            'new_owner_id' => $newOwner->id,
        ];

        $response = $this->put(route('workspace.update'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Workspace ownership delegated successfully.');

        $this->assertDatabaseHas('workspaces', [
            'id' => $this->workspace->id,
            'user_id' => $newOwner->id,
        ]);

        // Verify both old and new owner have full permissions
        $this->assertDatabaseHas('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'permissions' => json_encode([
                'users' => true,
                'files' => true,
                'folders' => true,
                'settings' => true,
            ]),
        ]);

        $this->assertDatabaseHas('memberships', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $newOwner->id,
            'permissions' => json_encode([
                'users' => true,
                'files' => true,
                'folders' => true,
                'settings' => true,
            ]),
        ]);
    });

    it('validates new owner exists', function () {
        $data = [
            'new_owner_id' => 'non-existent-id',
        ];

        $response = $this->put(route('workspace.update'), $data);

        $response->assertSessionHasErrors(['new_owner_id']);
    });

    it('cannot delegate to current owner', function () {
        $data = [
            'new_owner_id' => $this->user->id,
        ];

        $response = $this->put(route('workspace.update'), $data);

        $response->assertSessionHasErrors(['new_owner_id']);
    });
});

describe('Destroy Method (Soft Delete)', function () {
    beforeEach(function () {
        $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
        Session::put('current_workspace_id', $this->workspace->id);
    });

    it('owner can soft delete workspace', function () {
        $response = $this->delete(route('workspace.destroy', $this->workspace));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Workspace deleted successfully.');

        $this->assertSoftDeleted('workspaces', ['id' => $this->workspace->id]);
    });

    it('members cannot delete workspace', function () {
        $member = User::factory()->create();
        Membership::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'permissions' => ['settings' => true],
            'joined_at' => now(),
        ]);

        $this->actingAs($member);

        $response = $this->delete(route('workspace.destroy', $this->workspace));

        $response->assertForbidden();
    });

    it('soft deletes cascade correctly', function () {
        // Create related data
        $folder = \App\Models\Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $file = \App\Models\File::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->delete(route('workspace.destroy', $this->workspace));

        $response->assertRedirect();

        $this->assertSoftDeleted('workspaces', ['id' => $this->workspace->id]);
        $this->assertSoftDeleted('folders', ['id' => $folder->id]);
        $this->assertSoftDeleted('files', ['id' => $file->id]);
    });

    it('clears from session if current workspace', function () {
        $response = $this->delete(route('workspace.destroy', $this->workspace));

        $response->assertRedirect();

        expect(Session::get('current_workspace_id'))->toBeNull();
    });

    // Note: Workspace delete authorization is tested at the service layer
    // See WorkspaceAuthorizationServiceTest for comprehensive permission testing
});

describe('Authorization Edge Cases', function () {
    it('root users can access all workspaces', function () {
        $this->user->update(['role' => 'root']);
        $otherUser = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);
        Session::put('current_workspace_id', $otherWorkspace->id);

        $response = $this->get(route('workspace.edit'));

        $response->assertInertia(fn ($page) => $page
            ->component('workspaces/edit')
            ->has('workspace', fn ($prop) => $prop
                ->where('id', $otherWorkspace->id)
                ->etc()
            )
        );
    });

    it('workspace owner always has access regardless of membership', function () {
        $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
        Session::put('current_workspace_id', $workspace->id);

        // Remove membership record (simulating edge case)
        Membership::where('workspace_id', $workspace->id)
            ->where('user_id', $this->user->id)
            ->delete();

        $response = $this->get(route('workspace.edit'));

        $response->assertInertia(fn ($page) => $page
            ->component('workspaces/edit')
        );
    });

    it('validates workspace exists', function () {
        // User has no workspaces - should get invitation page
        $response = $this->get(route('workspace.edit'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page->component('invitation'));
    });
});
