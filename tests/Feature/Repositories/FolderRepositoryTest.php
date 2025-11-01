<?php

use App\Models\Folder;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\FolderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new FolderRepository;
    $this->user = User::factory()->create(['role' => 'user']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->admin->id]);
});

it('gets user folders for specific group', function () {
    // Create membership
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $folder1 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
    $folder2 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
    Folder::factory()->create(['workspace_id' => Workspace::factory()->create()->id]); // Other group

    $folders = $this->repository->getUserFolders($this->user, $this->workspace->id);

    expect($folders)->toHaveCount(2);
    expect($folders->pluck('id')->toArray())->toContain($folder1->id, $folder2->id);
});

it('gets user folders from all accessible groups', function () {
    $group2 = Workspace::factory()->create(['user_id' => $this->admin->id]);

    // Create memberships
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);
    Membership::create([
        'workspace_id' => $group2->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $folder1 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
    $folder2 = Folder::factory()->create(['workspace_id' => $group2->id]);

    $folders = $this->repository->getUserFolders($this->user);

    expect($folders)->toHaveCount(2);
    expect($folders->pluck('id')->toArray())->toContain($folder1->id, $folder2->id);
});

it('filters folders by search term', function () {
    // Create membership
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    Folder::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Test Folder']);
    Folder::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Another Folder']);

    $folders = $this->repository->getUserFolders($this->user, $this->workspace->id, ['search' => 'Test']);

    expect($folders)->toHaveCount(1);
    expect($folders->first()->name)->toBe('Test Folder');
});

it('filters empty folders', function () {
    // Create membership
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    // Create empty folder (no children, no files)
    $emptyFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);

    // Create folder with children (not empty) - this should NOT appear in empty filter
    $folderWithChildren = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
    $childFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id, 'parent_id' => $folderWithChildren->id]);

    $folders = $this->repository->getUserFolders($this->user, $this->workspace->id, ['filter' => 'empty']);

    expect($folders)->toHaveCount(1);
    expect($folders->first()->id)->toBe($emptyFolder->id);
});

it('gets folder counts for group', function () {
    Folder::factory()->create(['workspace_id' => $this->workspace->id]);
    Folder::factory()->create(['workspace_id' => $this->workspace->id]);
    Folder::factory()->create(['workspace_id' => $this->workspace->id])->delete(); // Soft deleted

    $counts = $this->repository->getFolderCounts($this->workspace->id);

    expect($counts)->toHaveKeys(['all', 'empty', 'deleted']);
    expect($counts['all'])->toBe(2);
    expect($counts['deleted'])->toBe(1);
});

it('gets folder tree with max depth', function () {
    $rootFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id, 'level' => 0]);
    $childFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id, 'parent_id' => $rootFolder->id, 'level' => 1]);
    $grandChildFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id, 'parent_id' => $childFolder->id, 'level' => 2]);

    $tree = $this->repository->getFolderTree($this->workspace->id, 1);

    expect($tree)->toHaveCount(2); // Root and child, not grandchild
    expect($tree->pluck('id')->toArray())->toContain($rootFolder->id, $childFolder->id);
    expect($tree->pluck('id')->toArray())->not->toContain($grandChildFolder->id);
});
