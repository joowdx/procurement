<?php

use App\Actions\Folders\CreateFolder;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->action = new CreateFolder;
});

it('creates root folder', function () {
    $data = [
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Folder',
        'description' => 'Test Description',
    ];

    $folder = $this->action->handle($data, $this->user);

    expect($folder)->toBeInstanceOf(Folder::class);
    expect($folder->name)->toBe('Test Folder');
    expect($folder->description)->toBe('Test Description');
    expect($folder->workspace_id)->toBe($this->workspace->id);
    expect($folder->parent_id)->toBeNull();
    expect($folder->level)->toBe(0);
    expect($folder->route)->toBe('Test Folder');
    expect($folder->order)->toBe(1);
});

it('creates subfolder with parent', function () {
    $parent = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'level' => 0,
        'order' => 1,
    ]);

    $data = [
        'workspace_id' => $this->workspace->id,
        'parent_id' => $parent->id,
        'name' => 'Child Folder',
        'description' => 'Child Description',
    ];

    $folder = $this->action->handle($data, $this->user);

    expect($folder->parent_id)->toBe($parent->id);
    expect($folder->level)->toBe(1);
    expect($folder->route)->toBe($parent->name.'/Child Folder');
    expect($folder->order)->toBe(1); // First child folder gets order 1
});

it('increments order for folders in same parent', function () {
    // Create first folder
    Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
    ]);

    $data = [
        'workspace_id' => $this->workspace->id,
        'name' => 'Second Folder',
    ];

    $folder = $this->action->handle($data, $this->user);

    expect($folder->order)->toBe(2);
});

it('handles empty description', function () {
    $data = [
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Folder',
    ];

    $folder = $this->action->handle($data, $this->user);

    expect($folder->description)->toBeNull();
});
