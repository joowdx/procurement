<?php

use App\Actions\Folders\UpdateFolder;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->folder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'level' => 0,
        'route' => 'Original Folder',
    ]);
    $this->action = new UpdateFolder;
});

it('updates folder with valid data', function () {
    $data = [
        'name' => 'Updated Folder Name',
        'description' => 'Updated Description',
    ];

    $updatedFolder = $this->action->handle($this->folder, $data, $this->user);

    expect($updatedFolder->name)->toBe('Updated Folder Name');
    expect($updatedFolder->description)->toBe('Updated Description');
    expect($updatedFolder->route)->toBe('Updated Folder Name');
});

it('updates only provided fields', function () {
    $originalDescription = $this->folder->description;
    $data = [
        'name' => 'Updated Folder Name',
    ];

    $updatedFolder = $this->action->handle($this->folder, $data, $this->user);

    expect($updatedFolder->name)->toBe('Updated Folder Name');
    expect($updatedFolder->description)->toBe($originalDescription);
});

it('handles empty description', function () {
    $data = [
        'name' => 'Updated Folder Name',
        'description' => '',
    ];

    $updatedFolder = $this->action->handle($this->folder, $data, $this->user);

    expect($updatedFolder->description)->toBeNull();
});

it('updates route when name changes', function () {
    $data = [
        'name' => 'Completely New Name',
    ];

    $updatedFolder = $this->action->handle($this->folder, $data, $this->user);

    expect($updatedFolder->route)->toBe('Completely New Name');
});
