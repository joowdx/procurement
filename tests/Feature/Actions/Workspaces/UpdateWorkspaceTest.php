<?php

use App\Actions\Workspaces\UpdateWorkspace;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->action = new UpdateWorkspace;
});

it('updates group with valid data', function () {
    $data = [
        'name' => 'Updated Group Name',
        'description' => 'Updated Description',
    ];

    $updatedGroup = $this->action->handle($this->workspace, $data, $this->user);

    expect($updatedGroup->name)->toBe('Updated Group Name');
    expect($updatedGroup->description)->toBe('Updated Description');
    expect($updatedGroup->slug)->toBe('updated-group-name');
});

it('updates only provided fields', function () {
    $data = [
        'name' => 'Updated Group Name',
    ];

    $originalDescription = $this->workspace->description;
    $updatedGroup = $this->action->handle($this->workspace, $data, $this->user);

    expect($updatedGroup->name)->toBe('Updated Group Name');
    expect($updatedGroup->description)->toBe($originalDescription);
});

it('handles empty description', function () {
    $data = [
        'name' => 'Updated Group Name',
        'description' => '',
    ];

    $updatedGroup = $this->action->handle($this->workspace, $data, $this->user);

    expect($updatedGroup->description)->toBeNull();
});

it('updates slug when name changes', function () {
    $data = [
        'name' => 'Completely New Name',
    ];

    $updatedGroup = $this->action->handle($this->workspace, $data, $this->user);

    expect($updatedGroup->slug)->toBe('completely-new-name');
});
