<?php

use App\Actions\Folders\ReorderFolders;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->action = new ReorderFolders;
});

it('reorders folders with new order', function () {
    $folder1 = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
    ]);
    $folder2 = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 2,
    ]);
    $folder3 = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 3,
    ]);

    $folders = [
        ['id' => $folder3->id, 'order' => 1],
        ['id' => $folder1->id, 'order' => 2],
        ['id' => $folder2->id, 'order' => 3],
    ];

    $result = $this->action->handle($folders, $this->user);

    expect($result)->toBeTrue();

    // Check new order
    $this->assertDatabaseHas('folders', [
        'id' => $folder3->id,
        'order' => 1,
    ]);
    $this->assertDatabaseHas('folders', [
        'id' => $folder1->id,
        'order' => 2,
    ]);
    $this->assertDatabaseHas('folders', [
        'id' => $folder2->id,
        'order' => 3,
    ]);
});

it('handles empty folders array', function () {
    $result = $this->action->handle([], $this->user);

    expect($result)->toBeTrue();
});
