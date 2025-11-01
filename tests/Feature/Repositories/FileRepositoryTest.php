<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\Membership;
use App\Models\Placement;
use App\Models\User;
use App\Models\Version;
use App\Models\Workspace;
use App\Repositories\FileRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new FileRepository;
    $this->user = User::factory()->create(['role' => 'user']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->admin->id]);
});

it('gets user files for specific group', function () {
    // Create membership
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $file1 = File::factory()->create(['workspace_id' => $this->workspace->id]);
    $file2 = File::factory()->create(['workspace_id' => $this->workspace->id]);
    File::factory()->create(['workspace_id' => Workspace::factory()->create()->id]); // Other group

    $files = $this->repository->getUserFiles($this->user, $this->workspace->id);

    expect($files)->toHaveCount(2);
    expect($files->pluck('id')->toArray())->toContain($file1->id, $file2->id);
});

it('gets user files from all accessible groups', function () {
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

    $file1 = File::factory()->create(['workspace_id' => $this->workspace->id]);
    $file2 = File::factory()->create(['workspace_id' => $group2->id]);

    $files = $this->repository->getUserFiles($this->user);

    expect($files)->toHaveCount(2);
    expect($files->pluck('id')->toArray())->toContain($file1->id, $file2->id);
});

it('filters files by type', function () {
    // Create membership
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    // Create files with specific types
    $pdfFile = File::factory()->create(['workspace_id' => $this->workspace->id, 'type' => 'application/pdf']);
    $jpegFile = File::factory()->create(['workspace_id' => $this->workspace->id, 'type' => 'image/jpeg']);

    // Update the versions to match the file types
    $pdfFile->versions()->first()->update(['disk' => 'local', 'path' => 'test.pdf']);
    $jpegFile->versions()->first()->update(['disk' => 'local', 'path' => 'test.jpg']);

    $files = $this->repository->getUserFiles($this->user, $this->workspace->id, ['type' => 'application/pdf']);

    expect($files)->toHaveCount(1);
    expect($files->first()->type)->toBe('application/pdf');
});

it('filters unplaced files', function () {
    // Create membership
    Membership::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'permissions' => ['users' => false, 'files' => true, 'folders' => true, 'settings' => true],
        'joined_at' => now(),
    ]);

    $placedFile = File::factory()->create(['workspace_id' => $this->workspace->id]);
    $unplacedFile = File::factory()->create(['workspace_id' => $this->workspace->id]);

    // Create placement for first file
    $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
    Placement::create([
        'file_id' => $placedFile->id,
        'folder_id' => $folder->id,
        'order' => 1,
    ]);

    $files = $this->repository->getUserFiles($this->user, $this->workspace->id, ['filter' => 'unplaced']);

    expect($files)->toHaveCount(1);
    expect($files->first()->id)->toBe($unplacedFile->id);
});

it('gets file counts for group', function () {
    File::factory()->create(['workspace_id' => $this->workspace->id]);
    File::factory()->create(['workspace_id' => $this->workspace->id]);
    File::factory()->create(['workspace_id' => $this->workspace->id])->delete(); // Soft deleted

    $counts = $this->repository->getFileCounts($this->workspace->id);

    expect($counts)->toHaveKeys(['all', 'unplaced', 'deleted']);
    expect($counts['all'])->toBe(2);
    expect($counts['deleted'])->toBe(1);
});

it('checks hash duplication within group', function () {
    $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

    // Update the existing version with our test hash
    $file->versions()->first()->update(['hash' => 'test-hash-123']);

    $duplicateFile = $this->repository->checkHashDuplication('test-hash-123', $this->workspace->id);

    expect($duplicateFile)->toBeInstanceOf(File::class);
    expect($duplicateFile->id)->toBe($file->id);
});

it('returns null for non-duplicate hash', function () {
    $result = $this->repository->checkHashDuplication('non-existent-hash', $this->workspace->id);

    expect($result)->toBeNull();
});

it('does not find duplicates across different groups', function () {
    $otherGroup = Workspace::factory()->create();
    $file = File::factory()->create(['workspace_id' => $otherGroup->id]);

    // Update the existing version with our test hash
    $file->versions()->first()->update(['hash' => 'test-hash-123']);

    $duplicateFile = $this->repository->checkHashDuplication('test-hash-123', $this->workspace->id);

    expect($duplicateFile)->toBeNull();
});
