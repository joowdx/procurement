<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user);
});

it('can create a workspace', function () {
    $data = [
        'name' => 'Test Workspace',
        'description' => 'Test Description',
    ];

    $response = $this->post(route('workspaces.store'), $data);

    $response->assertRedirect(route('workspace.edit'));
    $this->assertDatabaseHas('workspaces', [
        'name' => 'Test Workspace',
        'description' => 'Test Description',
        'user_id' => $this->user->id,
    ]);

    // Check that workspace is set as current workspace in session
    expect(Session::get('current_workspace_id'))->not->toBeNull();
});

it('can select a workspace', function () {
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

it('can edit current workspace', function () {
    $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    Session::put('current_workspace_id', $workspace->id);

    $response = $this->getJson(route('workspace.edit'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'workspace' => [
            'id',
            'name',
            'slug',
            'description',
            'user_id',
            'created_at',
            'updated_at',
        ],
        'stats',
    ]);
});

it('can update current workspace', function () {
    $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    Session::put('current_workspace_id', $workspace->id);

    $data = [
        'name' => 'Updated Workspace Name',
        'description' => 'Updated Description',
    ];

    $response = $this->put(route('workspace.update'), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'name' => 'Updated Workspace Name',
        'description' => 'Updated Description',
    ]);
});

it('can delegate workspace ownership', function () {
    $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $newOwner = User::factory()->create();
    Session::put('current_workspace_id', $workspace->id);

    $data = [
        'new_owner_id' => $newOwner->id,
    ];

    $response = $this->put(route('workspace.update'), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'user_id' => $newOwner->id,
    ]);
});

it('prevents non-admin users from creating workspaces', function () {
    $this->user->update(['role' => 'user']);

    $data = [
        'name' => 'Test Workspace',
        'description' => 'Test Description',
    ];

    $response = $this->post(route('workspaces.store'), $data);

    $response->assertForbidden();
});

it('prevents users from selecting workspaces they do not own or belong to', function () {
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

it('shows invitation page when user has no workspaces', function () {
    // Create a user with no workspaces
    $userWithoutWorkspaces = User::factory()->create(['role' => 'user']);
    $this->actingAs($userWithoutWorkspaces);

    $response = $this->get(route('workspace.edit'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('invitation'));
});
