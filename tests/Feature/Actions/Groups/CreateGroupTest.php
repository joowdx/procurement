<?php

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->action = new CreateWorkspace;
});

it('creates a workspace with valid data', function () {
    $data = [
        'name' => 'Test Group',
        'description' => 'Test Description',
    ];

    $workspace = $this->action->handle($data, $this->user);

    expect($workspace)->toBeInstanceOf(Workspace::class);
    expect($workspace->name)->toBe('Test Group');
    expect($workspace->description)->toBe('Test Description');
    expect($workspace->user_id)->toBe($this->user->id);
    expect($workspace->slug)->toBe('test-group');
});

it('generates slug from name', function () {
    $data = [
        'name' => 'My Awesome Group',
        'description' => 'Test Description',
    ];

    $workspace = $this->action->handle($data, $this->user);

    expect($workspace->slug)->toBe('my-awesome-group');
});

it('handles empty description', function () {
    $data = [
        'name' => 'Test Group',
    ];

    $workspace = $this->action->handle($data, $this->user);

    expect($workspace->description)->toBeNull();
});

it('creates membership record for owner', function () {
    $data = [
        'name' => 'Test Group',
        'description' => 'Test Description',
    ];

    $workspace = $this->action->handle($data, $this->user);

    $this->assertDatabaseHas('memberships', [
        'workspace_id' => $workspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
        'permissions' => json_encode([
            'users' => true,
            'files' => true,
            'folders' => true,
            'settings' => true,
        ]),
    ]);
});
